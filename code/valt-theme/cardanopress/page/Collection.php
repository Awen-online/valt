<?php

/**
 * Page template for displaying the user's personal Valt — their collection
 * of Cardano NFTs / digital collectables.
 *
 * This can be overridden by copying it to yourtheme/cardanopress/page/Collection.php.
 *
 * @package ThemePlate
 * @since   0.1.0
 */

cardanoPress()->compatibleHeader();

?>

<main class="container mx-auto px-4">
    <div class="py-8">

        <?php cardanoPress()->template('welcome-banner'); ?>

        <div class="mb-8 border-b pb-6" style="border-color: #493D3C;">
            <h1 class="text-3xl font-bold mb-1" style="color: #E8C48B;">
                Your Valt
            </h1>
            <p class="text-sm" style="color: #C5AD90;">
                Your personal collection of digital collectables. Share it with the world or keep it private — it&rsquo;s yours.
            </p>
        </div>

        <?php the_content(); ?>

        <ul class="flex flex-wrap -mx-4 list-none p-0">
            <?php cardanoPress()->template('collection-list'); ?>
        </ul>

    </div>
</main>

<?php

cardanoPress()->compatibleFooter();
