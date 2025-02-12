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

// Function to clean the widget content
function cleanWidgetContent($content) {
    $content = preg_replace('/^document\.write\("(.+)"\);$/s', '$1', $content);
    $content = str_replace(['\"', "\\'"], ['"', "'"], $content);
    $content = preg_replace('/\s*style="[^"]*"/i', '', $content);  // Remove inline styles
    $content = preg_replace('/<tfoot>.*?<\/tfoot>/is', '', $content);  // Remove the footer
    $content = preg_replace('/<th>Resultat<\/th>/i', '', $content);  // Remove the "Resultat" column
    $content = preg_replace('/<tr class="[^"]*">\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>.*?<\/td>\s*<\/tr>/is', '<tr><td>$1</td><td>$2</td><td>$3</td></tr>', $content);
    return trim($content);
}

// Function to extract matches
function extractMatches($content) {
    $matches = [];
    if (preg_match_all('/<tr>\s*<td>\s*(\d{4}-\d{2}-\d{2})(?:<!-- br ok -->\s*(\d{2}:\d{2}))?\s*<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>/is', $content, $matchesData, PREG_SET_ORDER)) {
        foreach ($matchesData as $match) {
            $date = $match[1];
            $time = isset($match[2]) && trim($match[2]) ? trim($match[2]) : '00:00';
            $competition = strip_tags($match[3]);
            $game = strip_tags($match[4]);
            $monthName = getMonthName($date);

            $matches[] = [
                'date' => "$date $time",
                'month' => $monthName,
                'competition' => $competition,
                'match' => $game
            ];
        }
    }
    return $matches;
}

// Function to get Swedish month names
function getMonthName($date) {
    $months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Mars', '04' => 'April',
        '05' => 'Maj', '06' => 'Juni', '07' => 'Juli', '08' => 'Augusti',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'December'
    ];
    $monthNum = date('m', strtotime($date));
    return $months[$monthNum] ?? '';
}

// Process each widget URL and merge the matches
foreach ($widgetUrls as $index => $url) {
    $cacheFile = "widget_data_$index.html";
    if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > $cacheTime) {
        $content = fetchContentWithCurl($url);
        if ($content) {
            file_put_contents($cacheFile, $content);
        }
    } else {
        $content = file_get_contents($cacheFile);
    }

    if ($content) {
        $cleanContent = cleanWidgetContent($content);
        $matches = extractMatches($cleanContent);
        $allMatches = array_merge($allMatches, $matches);
    }
}

// Sort matches by date
usort($allMatches, function ($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Generate the HTML table rows
$lastMonth = '';
$tableRows = '';
foreach ($allMatches as $match) {
    if ($match['month'] !== $lastMonth) {
        $tableRows .= "<tr><td colspan='3'><strong>{$match['month']}</strong></td></tr>";
        $lastMonth = $match['month'];
    }
    $tableRows .= "<tr>
        <td>{$match['date']}</td>
        <td>{$match['competition']}</td>
        <td>{$match['match']}</td>
    </tr>";
}

function generateICS($matches) {
    $icsContent = "BEGIN:VCALENDAR\r\n";
    $icsContent .= "VERSION:2.0\r\n";
    $icsContent .= "CALSCALE:GREGORIAN\r\n";
    $icsContent .= "X-WR-CALNAME:Sirius - Akademilagens matcher\r\n";  // Calendar name
    $icsContent .= "X-WR-TIMEZONE:Europe/Stockholm\r\n";     // Set timezone (optional but recommended)
    
    foreach ($matches as $match) {
        $date = DateTime::createFromFormat('Y-m-d H:i', $match['date']);
        if ($date) {
            $icsContent .= "BEGIN:VEVENT\r\n";
            $icsContent .= "UID:" . uniqid() . "@siriusfotboll.se\r\n";  // Unique ID for the event
            $icsContent .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $icsContent .= "DTSTART:" . $date->format('Ymd\THis') . "\r\n";
            $icsContent .= "DTEND:" . $date->modify('+2 hours')->format('Ymd\THis') . "\r\n";
            $icsContent .= "SUMMARY:" . $match['match'] . "\r\n";
            $icsContent .= "DESCRIPTION:" . $match['competition'] . "\r\n";
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
                <table class="clCommonGrid">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Tävling</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody class="clGrid">
                        <?= $tableRows ?: '<tr><td colspan="3">Inga matcher hittades</td></tr>'; ?>
                    </tbody>
                </table>
                <a href="?download=true" class="btn btn-primary mt-3">Ladda ner kalenderfil (.ics)</a>
            </div>
        </div>
    </div>
    <p>
           <!-- the elegant footer from https://github.com/hjelmua/matcher/ -->
       </p>
</body>
</html>
