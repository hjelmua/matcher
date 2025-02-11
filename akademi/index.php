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
$allMatches = [];
$tableRows = '';

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
    if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > $cacheTime) {
        $content = fetchContentWithCurl($url);
        if ($content) {
            file_put_contents($cacheFile, $content);
            echo "Saved $cacheFile<br>";
        } else {
            echo "<!-- Failed to download content from $url<br> -->";
        }
    } else {
        echo "<!-- Using cached file $cacheFile<br> -->";
    }
}

// Function to clean the widget content
function cleanWidgetContent($content) {
    $content = preg_replace('/^document\.write\("(.+)"\);$/s', '$1', $content);
    $content = str_replace('\"', '"', $content);
    $content = str_replace("\\'", "'", $content);
    $content = preg_replace('/\s*style="[^"]*"/i', '', $content);  // Remove inline styles
    return trim($content);
}

// Function to extract match data
function extractMatches($content) {
    $matches = [];
    if (preg_match_all('/<tr class="[^"]*">\s*<td>(\d{4}-\d{2}-\d{2})<!-- br ok -->\s*(\d{2}:\d{2})?<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>/is', $content, $matchesData, PREG_SET_ORDER)) {
        foreach ($matchesData as $match) {
            $date = strip_tags($match[1]);
            $time = isset($match[2]) ? trim($match[2]) : '00:00';
            $competition = strip_tags($match[3]);
            $game = strip_tags($match[4]);
            $matches[] = [
                'date' => "$date $time",
                'competition' => $competition,
                'match' => $game
            ];
        }
    }
    return $matches;
}

// Process each cached file
for ($i = 0; $i < count($widgetUrls); $i++) {
    $cacheFile = "widget_data_$i.html";
    if (file_exists($cacheFile)) {
        $content = file_get_contents($cacheFile);
        $cleanContent = cleanWidgetContent($content);
        
        // Debug: Save cleaned content to a file
//        file_put_contents("debug_clean_content_$i.html", $cleanContent);

        $matches = extractMatches($cleanContent);
        $allMatches = array_merge($allMatches, $matches);
    }
}

// Sort matches by date
usort($allMatches, function ($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Generate the HTML table rows
foreach ($allMatches as $match) {
    $tableRows .= "<tr>
        <td>{$match['date']}</td>
        <td>{$match['competition']}</td>
        <td>{$match['match']}</td>
    </tr>";
}

// Function to generate an ICS file from matches
function generateICS($matches) {
    $icsContent = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\n";
    
    foreach ($matches as $match) {
        $date = DateTime::createFromFormat('Y-m-d H:i', $match['date']);
        if ($date) {
            $icsContent .= "BEGIN:VEVENT\r\n";
            $icsContent .= "DTSTART:" . $date->format('Ymd\THis') . "\r\n";
            $icsContent .= "DTEND:" . $date->modify('+2 hours')->format('Ymd\THis') . "\r\n";
            $icsContent .= "SUMMARY:" . trim($match['match']) . "\r\n";
            $icsContent .= "DESCRIPTION:" . trim($match['competition']) . "\r\n";
            $icsContent .= "END:VEVENT\r\n";
        }
    }
    
    $icsContent .= "END:VCALENDAR\r\n";
    return $icsContent;
}

// Generate the ICS file if download is requested
if (isset($_GET['download'])) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="akademilagens_matcher.ics"');
    ob_clean();
    echo generateICS($allMatches);
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademilagens matcher</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
        body { background-color: #F7F3EA; font-family: 'Satoshi', sans-serif !important; }
        .container { max-width: 800px; margin-top: 50px; }
        .card { padding: 1px; border-radius: 10px; }
       
       h1 {font-family: 'LeagueGothic', sans-serif; font-size: 3.5rem !important; } 
       
.btn-primary {
    background-color: #0058A2 !important;
    border-color: #0058A2 !important;
    display: inline-block !important;  /* Ensures the button wraps only its content */
    width: auto !important;            /* Prevents it from stretching full width */
    padding: 10px 20px;               /* Adjust padding for better button size */
    text-align: center;               /* Center the text inside the button */
}

.btn-primary:hover {
    background-color: #004B8A !important; /* Slightly darker for hover */
    border-color: #004B8A !important;
}

       .clGrid {
           font-size: 14px !important; /* Adjust to your desired size */
           line-height: 1.5 !important; /* For better readability */
           color: #FFF !important; /* Ensure text color is consistent */
        }
       .clCommonGrid {
            border: 1px solid #dee2e6 !important;
            border-collapse: collapse !important;
            width: 100% !important;
        }
       .clGrid td {
            font-size: 12px !important;
            padding: 10px !important; /* Optional, for spacing */
        }

        .clCommonGrid th {
            background-color: #000 !important;
            color: #FFF !important;
            padding: 12px !important;
            text-align: left !important;
            border: 1px solid #dee2e6 !important;
        }

        .clCommonGrid td {
            padding: 12px !important;
            border: 1px solid #dee2e6 !important;
            color: #FFF !important;
            background-color: #0058A2 !important;
        }

        .clCommonGrid tbody tr:nth-child(even) td {
            background-color: #005CB1 !important;
        }

        .clCommonGrid tbody tr:hover td {
            background-color: #FFE100 !important;
            color: #000 !important;
        }

        .clCommonGrid tbody a {
            color: #FFF !important;
            text-decoration: none !important;
        }

        .clCommonGrid tbody a:hover {
            color: #000 !important;
            text-decoration: underline !important;
        }
      		
		@font-face {
		        font-family: "LeagueGothic";
		        font-weight: 700;
		        font-style: bold;
		        src: url("https://functions.siriusfotboll.org/font/LeagueGothic-Regular-VariableFont_wdth.woff") format("woff"), url("https://functions.siriusfotboll.org/font/LeagueGothic-Regular-VariableFont_wdth.ttf") format("truetype")
                }
        
        @font-face {
		      
                font-family: "Rogeu";
		        font-weight: 400;
		        font-style: normal;
		        src: url("https://functions.siriusfotboll.org/font/Rogeu.woff") format("woff"), url("https://functions.siriusfotboll.org/font/Rogeu.ttf") format("truetype")
		    }
		@font-face {
				        font-family: "Satoshi";
				        font-weight: 400;
				        font-style: normal;
				        src: url("https://functions.siriusfotboll.org/font/Satoshi-Medium.woff") format("woff"), url("https://functions.siriusfotboll.org/font/Satoshi-Medium.ttf") format("truetype")
			}

		@font-face {
		        font-family: "Satoshi";
		        font-weight: 700;
		        font-style: bold;
		        src: url("https://functions.siriusfotboll.org/font/Satoshi-Bold.woff") format("woff"), url("https://functions.siriusfotboll.org/font/Satoshi-Bold.ttf") format("truetype")
		    }
    </style>
</head>
<body>
    <div class="container mt-5">
 <div class="d-flex justify-content-between align-items-center mb-4">
    
    <h1>Akademilagens matcher</h1>
    <img src="https://functions.siriusfotboll.org/logo/Sirius_2021_RGB.webp" alt="IK Sirius Fotboll 1907" style="max-width: 100px; height: auto;">
  </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="clCommonGrid clear">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Tävling</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody class="clGrid">
                        <?php echo $tableRows ?: '<tr><td colspan="3">No matches found</td></tr>'; ?>
                    </tbody>
                </table>
                <a href="?download=true" class="btn btn-primary mt-3">Ladda ner en kalenderfil med akademilagens matcher (.ics)</a>
            </div>
        </div>
    </div>
    <p>
        <!-- the elegant footer -->
    </p>
</body>
</html>
