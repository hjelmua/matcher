<?php
$widgets = [
    [
        'cacheFile' => 'dam_widget_cache.html',
        'widgetUrl' => 'https://uppland.svenskfotboll.se/widget.aspx?scr=teamresult&flid=309070'
    ],
    [
        'cacheFile' => 'widget_cache.html',
        'widgetUrl' => 'https://www.svenskfotboll.se/widget.aspx?scr=clubfixturelist&feid=7884'
    ]
];

$cacheTime = 14400; // 4 hours in seconds

function fetchContentWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // Timeout after 10 seconds
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Script');

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL error: " . curl_error($ch));
        $response = false;
    }
    curl_close($ch);

    return $response;
}

// Process each widget
foreach ($widgets as $widget) {
    $cacheFile = $widget['cacheFile'];
    $widgetUrl = $widget['widgetUrl'];

    echo "Processing cache for $cacheFile...<br>";

    if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > $cacheTime) {
        $content = fetchContentWithCurl($widgetUrl);
        if ($content) {
            file_put_contents($cacheFile, $content);
            echo "Saved $cacheFile<br>";
        } else {
            echo "Failed to fetch content for $widgetUrl<br>";
        }
    } else {
        echo "Using cached file $cacheFile<br>";
    }
}

echo "Cache update completed.";
?>
