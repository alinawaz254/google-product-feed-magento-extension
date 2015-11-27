<?php
set_time_limit(0);
require_once 'app/Mage.php';
Mage::app(Mage::app()->getStore()->getCode());

$title                   = isset($_POST['title']) && $_POST['title'] != '' ? $_POST['title'] : '';
$link                    = isset($_POST['link']) && $_POST['link'] != '' ? $_POST['link'] : '';
$description             = isset($_POST['description']) && $_POST['description'] != '' ? $_POST['description'] : '';
$published_date          = isset($_POST['published_date']) && $_POST['published_date'] != '' ? $_POST['published_date'] : '';
$google_product_category = isset($_POST['google_product_category']) && $_POST['google_product_category'] > 0 ? $_POST['google_product_category'] : '';

try {
    $handle    = fopen('google-product-feed.xml', 'w');
    $feed_lines[] = '<?xml version="1.0"?>';
    $feed_lines[] = '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
    $feed_lines[] = '    <channel>';

    if (trim($title) != '') {
        $feed_lines[] = '        <title>' . trim($title) . '</title>';
    }

    if (trim($link) != '') {
        $feed_lines[] = '        <link>' . trim($link) . '</link>';
    }

    if (trim($description) != '') {
        $feed_lines[] = '        <description>' . trim($description) . '</description>';
    }

    if (trim($published_date) != '') {
        $feed_lines[] = '        <pubDate>' . trim($published_date) . '</pubDate>';
    }

    $products = Mage::getModel('catalog/product')->getCollection();
    $products->addAttributeToFilter('status', 1);//enabled
    $products->addAttributeToFilter('visibility', 4); //catalog, search
    $products->addAttributeToSelect('*');
    $prodIds = $products->getAllIds();
    $product = Mage::getModel('catalog/product');
    $categoryModel = Mage::getModel('catalog/category');
    $tagModel = Mage::getModel('tag/tag');

    foreach ($prodIds as $productId) {
        $product->load($productId);
        $productUrl = $product->getProductUrl();
        $productUrl = str_replace('/' . basename($_SERVER['PHP_SELF']), '/index.php', $productUrl); // include index.php in url
        // $productUrl = str_replace('/' . basename($_SERVER['PHP_SELF']), '', $productUrl); // do not included index.php in url

        $feed_lines[] = '        <item>';
        $feed_lines[] = '            <g:id>' . $product->getId() . '</g:id>';
        $feed_lines[] = '            <title>' . htmlspecialchars($product->getName()) . '</title>';
        $feed_lines[] = '            <link>' . $productUrl . '</link>';

        if ($product->getSpecialPrice()) {
            $feed_lines[] = '            <g:price>' . $product->getSpecialPrice() . '</g:price>';
        } else {
            $feed_lines[] = '            <g:price>' . $product->getPrice() . '</g:price>';
        }

        foreach ($product->getCategoryIds() as $_categoryId) {
            $category = $categoryModel->load($_categoryId);
            $product_categories[] = $category->getName();
        }

        $tags_collection= $tagModel->getResourceCollection()
            ->addPopularity()
            ->addStatusFilter($tagModel->getApprovedStatus())
            ->addProductFilter($product->getId())
            ->setFlag('relation', true)
            ->addStoreFilter(Mage::app()->getStore()->getId())
            ->setActiveFilter()
            ->load();

        $productTags = $tags_collection->getItems();

        foreach ($productTags as $tag) {
            $product_tags[] = $tag->getName();
        }

        $feed_lines[] = '            <description>' . htmlspecialchars(strip_tags($product->getDescription())) . '</description>';
        $feed_lines[] = '            <g:mpn>' . htmlspecialchars($product->getSku()) . '</g:mpn>';
        $feed_lines[] = '            <g:image_link>' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage() . '</g:image_link>';
        $feed_lines[] = '            <g:quantity>' . number_format(Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty(), 0, '', '') . '</g:quantity>';
        $feed_lines[] = '            <g:availability>' . $product->getIsInStock() . '</g:availability>';
        $feed_lines[] = '            <g:manufacturer></g:manufacturer>';
        $feed_lines[] = '            <g:google_product_category>' . $google_product_category . '</g:google_product_category>';
        $feed_lines[] = '            <g:identifier_exists>TRUE</g:identifier_exists>';
        $feed_lines[] = '            <g:brand>' . htmlspecialchars($product->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($product)) . '</g:brand>';
        $feed_lines[] = '            <g:product_type>' . (isset($product_categories[0]) ? htmlspecialchars($product_categories[0]) : '') . '</g:product_type>';
        $feed_lines[] = '            <g:shipping_weight>' . $product->getWeight() . '</g:shipping_weight>';
        $feed_lines[] = '            <g:condition>new</g:condition>';

        for ($x = 0; $x <= 4; $x++) {
            if (isset($product_tags[$x]) && trim($product_tags[$x]) != '') {
                $feed_lines[] = '            <g:custom_label_' . $x . '>' . htmlspecialchars(trim($product_tags[0])) . '</g:custom_label_' . $x . '>';
            }
        }

        $feed_lines[] = '            <g:shipping>';
        $feed_lines[] = '                <g:price>' . Mage::getStoreConfig('carriers/flatrate/price') . '</g:price>';
        $feed_lines[] = '            </g:shipping>';
        $feed_lines[] = '        </item>';

        unset($product_tags);
    }

    $feed_lines[] = '    </channel>';
    $feed_lines[] = '</rss>';

    fwrite($handle, implode("\r\n", $feed_lines));
    fflush($handle);
    fclose($handle);
    echo 'success';
     
} catch(Exception $e) {
    die($e->getMessage());
}
?>