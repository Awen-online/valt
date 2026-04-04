"""
afrocharts-api-sync.py
Fetches songs from the Afrocharts REST API and upserts them as Song CPTs
in WordPress via the WP REST API (application-password auth).

Prerequisites
-------------
1. In wp-admin → Pods Admin → Edit Pod → song:
   - Enable REST API (show_in_rest = true, rest_base = song)
2. Register the Pods meta fields with show_in_rest so they are writable
   via the REST API.  Add the following to valt-theme/functions.php (or a
   dedicated functions/rest.php file):

   add_action('init', function() {
       foreach (['afrocharts_id', 'duration', 'track_number'] as $key) {
           register_post_meta('song', $key, [
               'show_in_rest'  => true,
               'single'        => true,
               'type'          => 'string',
               'auth_callback' => '__return_true',
           ]);
       }
   });

3. Create a WordPress application password:
   wp-admin → Users → {your user} → Application Passwords
   Paste it below as WP_APP_PASSWORD (spaces included are fine).

Usage
-----
    python afrocharts-api-sync.py

Audio files are self-hosted and uploaded manually; this script only
populates text metadata (title, afrocharts_id, duration, track_number).
"""

import requests
import base64
import logging
import re
import os

# ---------------------------------------------------------------------------
# Configuration — set these as environment variables or in a .env file
# ---------------------------------------------------------------------------
WP_URL          = os.environ.get('WP_URL', 'http://valt.local')
WP_USER         = os.environ.get('WP_USER', 'admin')
WP_APP_PASSWORD = os.environ.get('WP_APP_PASSWORD', '')  # WP application password

AFROCHARTS_PUBLIC_KEY = os.environ.get('AFROCHARTS_PUBLIC_KEY', '')
AFROCHARTS_SECRET_KEY = os.environ.get('AFROCHARTS_SECRET_KEY', '')
# ---------------------------------------------------------------------------

logging.basicConfig(
    filename='afrocharts_sync.log',
    level=logging.INFO,
    format='%(asctime)s %(levelname)s %(message)s'
)

# Afrocharts auth header
_afc_encoded = base64.b64encode(
    f'{AFROCHARTS_PUBLIC_KEY}:{AFROCHARTS_SECRET_KEY}'.encode()
).decode()
AFC_HEADERS = {'Authorization': f'Basic {_afc_encoded}'}

# WP REST auth tuple
WP_AUTH = (WP_USER, WP_APP_PASSWORD)


def clean_html(content):
    """Strip HTML tags from an error response."""
    text = content.decode() if isinstance(content, bytes) else content
    text = text.replace('<br />', '\n')
    return re.sub(r'<[^>]+>', '', text)


# ---------------------------------------------------------------------------
# Afrocharts
# ---------------------------------------------------------------------------

def get_afrocharts_songs():
    """Return all songs from the Afrocharts API."""
    url = 'https://api.afrocharts.com/v1/songs'
    try:
        resp = requests.get(url, headers=AFC_HEADERS, timeout=30)
        if resp.status_code == 200:
            return resp.json().get('data', [])
        logging.error('Afrocharts %s: %s', resp.status_code, clean_html(resp.content))
    except Exception as exc:
        logging.error('Afrocharts exception: %s', exc)
    return []


# ---------------------------------------------------------------------------
# WordPress REST API helpers
# ---------------------------------------------------------------------------

def get_existing_wp_songs():
    """
    Returns a dict of { afrocharts_id: wp_post_id } for all existing Song CPTs.
    Paginates through all pages.
    """
    existing = {}
    page = 1
    while True:
        resp = requests.get(
            f'{WP_URL}/wp-json/wp/v2/song',
            params={'per_page': 100, 'page': page, '_fields': 'id,meta'},
            auth=WP_AUTH,
            timeout=30,
        )
        if resp.status_code == 400:
            # 400 means we've gone past the last page
            break
        if resp.status_code != 200:
            logging.error('WP GET songs page %d: %s', page, resp.status_code)
            break
        data = resp.json()
        if not data:
            break
        for post in data:
            afc_id = (post.get('meta') or {}).get('afrocharts_id', '')
            if afc_id:
                existing[str(afc_id)] = post['id']
        # Check total pages via header
        total_pages = int(resp.headers.get('X-WP-TotalPages', 1))
        if page >= total_pages:
            break
        page += 1
    return existing


def create_wp_song(song):
    """
    POST a new Song CPT to WordPress.
    Returns (True, post_id) on success, (False, error_message) on failure.
    """
    # Normalise common Afrocharts field names — adjust if their schema differs
    title        = song.get('title') or song.get('name') or 'Untitled'
    afc_id       = str(song.get('id', ''))
    duration     = str(song.get('duration', ''))
    track_number = str(song.get('track_number') or song.get('track', ''))

    payload = {
        'title':  title,
        'status': 'publish',
        'meta': {
            'afrocharts_id':  afc_id,
            'duration':       duration,
            'track_number':   track_number,
        },
    }

    resp = requests.post(
        f'{WP_URL}/wp-json/wp/v2/song',
        json=payload,
        auth=WP_AUTH,
        timeout=30,
    )

    if resp.status_code in (200, 201):
        return True, resp.json().get('id')
    return False, f'{resp.status_code} {resp.text[:200]}'


def update_wp_song(post_id, song):
    """
    PATCH an existing Song CPT with fresh metadata.
    Returns (True, post_id) on success, (False, error_message) on failure.
    """
    payload = {
        'meta': {
            'duration':     str(song.get('duration', '')),
            'track_number': str(song.get('track_number') or song.get('track', '')),
        },
    }

    resp = requests.post(
        f'{WP_URL}/wp-json/wp/v2/song/{post_id}',
        json=payload,
        auth=WP_AUTH,
        timeout=30,
    )

    if resp.status_code in (200, 201):
        return True, post_id
    return False, f'{resp.status_code} {resp.text[:200]}'


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    print('Fetching songs from Afrocharts…')
    afc_songs = get_afrocharts_songs()
    if not afc_songs:
        print('No songs returned from Afrocharts.')
        return

    print(f'  {len(afc_songs)} songs retrieved.')

    print('Fetching existing WordPress songs…')
    existing = get_existing_wp_songs()
    print(f'  {len(existing)} songs already in WordPress.')

    created  = 0
    updated  = 0
    errors   = 0

    for song in afc_songs:
        afc_id = str(song.get('id', ''))

        if afc_id in existing:
            # Update metadata for known songs
            ok, result = update_wp_song(existing[afc_id], song)
            if ok:
                updated += 1
            else:
                errors += 1
                logging.error('Update failed for afc_id=%s: %s', afc_id, result)
        else:
            # Create new song CPT
            ok, result = create_wp_song(song)
            if ok:
                created += 1
                logging.info('Created song afc_id=%s → post %s', afc_id, result)
            else:
                errors += 1
                logging.error('Create failed for afc_id=%s: %s', afc_id, result)

    print(f'\nDone: {created} created, {updated} updated, {errors} errors.')
    if errors:
        print('Check afrocharts_sync.log for details.')


if __name__ == '__main__':
    main()
