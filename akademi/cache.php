<?php
$widgetUrls = [
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=55837',
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=56401',
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=169975',
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=199240',
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=330290',
    'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=330289'
];

$cacheTime = 14400; // 4 hours in seconds

// Function to fetch content with cURL
function fetchContentWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Script');
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Download and cache each file
foreach ($widgetUrls as $index => $url) {
    $cacheFile = "widget_data_$index.html";
    $content = fetchContentWithCurl($url);
    if ($content) {
        file_put_contents($cacheFile, $content);
        echo "Saved $cacheFile<br>";
    } else {
        echo "Failed to download content from $url<br>";
    }
}

echo "Cache updated successfully.";
