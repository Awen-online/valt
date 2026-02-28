import requests
import base64
import xml.etree.ElementTree as ET
from xml.dom import minidom
import logging
import re

# Configure logging
logging.basicConfig(filename='afrocharts_sync.log', level=logging.ERROR, format='%(asctime)s %(message)s')

# API credentials
public_key = 'YOUR_AFROCHARTS_PUBLIC_KEY'
secret_key = 'YOUR_AFROCHARTS_SECRET_KEY'

# Encode credentials to base64
credentials = f'{public_key}:{secret_key}'
encoded_credentials = base64.b64encode(credentials.encode()).decode()

# API endpoint
base_url = 'https://api.afrocharts.com/v1'
songs_endpoint = f'{base_url}/songs'

# Headers for API request
headers = {
    'Authorization': f'Basic {encoded_credentials}'
}

# Function to clean up the HTML error message
def clean_html_error(html_content):
    # Replace <br> tags with newlines
    text_content = html_content.decode().replace('<br />', '\n')
    
    # Remove any remaining HTML tags
    text_content = re.sub(r'<[^>]+>', '', text_content)
    
    return text_content

# Function to get songs from the API
def get_songs():
    try:
        response = requests.get(songs_endpoint, headers=headers)
        if response.status_code == 200:
            try:
                return response.json().get('data', [])
            except ValueError as e:
                cleaned_error = clean_html_error(response.content)
                logging.error(f"Error parsing JSON: {e}")
                logging.error(f"Cleaned Error Response:\n{cleaned_error}")
                return []
        else:
            cleaned_error = clean_html_error(response.content)
            logging.error(f"Failed to fetch songs: {response.status_code}")
            logging.error(f"Request URL: {response.url}")
            logging.error(f"Cleaned Error Response:\n{cleaned_error}")
            return []
    except Exception as e:
        logging.error(f"Exception occurred: {e}")
        logging.error(f"Request URL: {songs_endpoint}")
        return []

# Function to create or update local XML file with the retrieved data
def sync_to_xml(songs, xml_file='songs.xml'):
    root = ET.Element("songs")

    for song in songs:
        song_element = ET.SubElement(root, "song")
        for key, value in song.items():
            child = ET.SubElement(song_element, key)
            child.text = value

    tree = ET.ElementTree(root)
    
    # Pretty print the XML
    xml_str = minidom.parseString(ET.tostring(root)).toprettyxml(indent="   ")
    with open(xml_file, 'w') as f:
        f.write(xml_str)

# Main function to sync API data with XML file
def main():
    songs = get_songs()
    if songs:
        sync_to_xml(songs)
        print(f"Synced {len(songs)} songs to the XML file.")
    else:
        print("No data to sync.")

if __name__ == '__main__':
    main()
