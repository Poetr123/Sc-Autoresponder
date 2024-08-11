<?php
// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// get posted data
$data = json_decode(file_get_contents("php://input"));

// make sure json data is not incomplete
if (
    !empty($data->query) &&
    !empty($data->appPackageName) &&
    !empty($data->messengerPackageName) &&
    !empty($data->query->sender) &&
    !empty($data->query->message)
) {
    // package name of AutoResponder to detect which AutoResponder the message comes from
    $appPackageName = $data->appPackageName;
    // package name of messenger to detect which messenger the message comes from
    $messengerPackageName = $data->messengerPackageName;
    // name/number of the message sender (like shown in the Android notification)
    $sender = $data->query->sender;
    // text of the incoming message
    $message = strtolower(trim($data->query->message)); // convert message to lowercase and trim spaces
    // is the sender a group? true or false
    $isGroup = $data->query->isGroup;
    // name/number of the group participant who sent the message if it was sent in a group, otherwise empty
    $groupParticipant = $data->query->groupParticipant;
    // id of the AutoResponder rule which has sent the web server request
    $ruleId = $data->query->ruleId;
    // is this a test message from AutoResponder? true or false
    $isTestMessage = $data->query->isTestMessage;
    
    // process messages here
    $responses = array();

    if ($message == '?menu') {
        $responses = array(
            array("message" => "
1. ?translate <text> / <target_language> :
   Menampilkan terjemahan kata/kalimat

2. ?kalkulator <expression> : 
   Menampilkan hasil kalkulasi pengguna
            
3. ?wikipedia <query> :
   Menampilkan hasil pencarian Wikipedia

4. ?chatbot <message> :
   Berbicara dengan chatbot

5. ?igstalk <username> :
   Menampilkan informasi profil Instagram

6. ?mcscheck <IP_server> :
   Menampilkan status server Minecraft

7. ?mcsbcheck <IP_server> :
   Menampilkan status server Minecraft Bedrock
")
        );
    } elseif (strpos($message, '?translate') === 0) {
        $parts = explode(' / ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?translate <text> / <target_language>")
            );
        } else {
            $textAndLang = explode(' ', $parts[0], 2);
            $text = urlencode($textAndLang[1]);
            $targetLang = urlencode($parts[1]);

            $translateUrl = "https://skizo.tech/api/translate?apikey=poetz&text=" . $text . "&lang=" . $targetLang;

            $translationResponse = file_get_contents($translateUrl);
            $translationData = json_decode($translationResponse, true);
            $translatedText = isset($translationData['result']) ? $translationData['result'] : "Terjemahan tidak ditemukan.";

            $responses = array(
                array("message" => "Hasil terjemahan: " . $translatedText)
            );
        }
    } elseif (strpos($message, '?kalkulator') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?kalkulator <expression>")
            );
        } else {
            $expression = $parts[1];
            try {
                $result = eval('return ' . $expression . ';');
                $responses = array(
                    array("message" => "Hasil kalkulasi: " . $result)
                );
            } catch (ParseError $e) {
                $responses = array(
                    array("message" => "Ekspresi tidak valid.")
                );
            }
        }
    } elseif (strpos($message, '?wikipedia') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?wikipedia <query>")
            );
        } else {
            $query = str_replace(' ', '_', $parts[1]);
            $wikiUrl = "https://id.wikipedia.org/w/api.php?action=query&list=search&srsearch=" . urlencode($query) . "&format=json";

            $wikiResult = file_get_contents($wikiUrl);
            $searchResult = json_decode($wikiResult, true);
            if (!empty($searchResult['query']['search'])) {
                $wikiSnippet = strip_tags($searchResult['query']['search'][0]['snippet']);
                $responses = array(
                    array("message" => "Hasil pencarian Wikipedia: " . $wikiSnippet)
                );
            } else {
                $responses = array(
                    array("message" => "Tidak ada hasil yang ditemukan di Wikipedia.")
                );
            }
        }
    } elseif (strpos($message, '?chatbot') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?chatbot <message>")
            );
        } else {
            $chatMessage = urlencode($parts[1]);
            $chatbotUrl = "https://skizo.tech/api/openaiv2?apikey=poetz&text=" . $chatMessage;

            $chatbotResponse = file_get_contents($chatbotUrl);
            $responseArray = json_decode($chatbotResponse, true);

            if (isset($responseArray['result'])) {
                $responses = array(
                    array("message" => $responseArray['result'])
                );
            } else {
                $responses = array(
                    array("message" => "Maaf, saya tidak mengerti permintaan Anda. Silakan coba lagi.")
                );
            }
        }
    } elseif (strpos($message, '?igstalk') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?igstalk <username>")
            );
        } else {
            $username = urlencode($parts[1]);
            $igstalkUrl = "https://skizo.tech/api/igstalk?apikey=poetz&user=" . $username;

            $igstalkResponse = file_get_contents($igstalkUrl);
            $igstalkData = json_decode($igstalkResponse, true);

            if (isset($igstalkData['username'])) {
                $responses = array(
                    array("message" => "Username: " . $igstalkData['username'] . "\n" .
                                        "Full Name: " . $igstalkData['fullname'] . "\n" .
                                        "Posts: " . $igstalkData['posts'] . "\n" .
                                        "Following: " . $igstalkData['following'] . "\n" .
                                        "Followers: " . $igstalkData['followers'] . "\n" .
                                        "Bio: " . $igstalkData['bio'])
                );
            } else {
                $responses = array(
                    array("message" => "Tidak dapat menemukan informasi untuk username tersebut.")
                );
            }
        }
    } elseif (strpos($message, '?mcscheck') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?mcscheck <IP_server>")
            );
        } else {
            $ipServer = urlencode($parts[1]);
            $mcsCheckUrl = "https://api.mcsrvstat.us/3/" . $ipServer;

            $mcsCheckResponse = file_get_contents($mcsCheckUrl);
            $mcsCheckData = json_decode($mcsCheckResponse, true);

            $status = isset($mcsCheckData['online']) && $mcsCheckData['online'] ? 'online' : 'offline';
            $port = isset($mcsCheckData['port']) ? $mcsCheckData['port'] : 'unknown';
            $hostname = isset($mcsCheckData['hostname']) ? $mcsCheckData['hostname'] : 'unknown';
            $onlinePlayers = isset($mcsCheckData['players']['online']) ? $mcsCheckData['players']['online'] : 'unknown';
            $maxPlayers = isset($mcsCheckData['players']['max']) ? $mcsCheckData['players']['max'] : 'unknown';

            $responses = array(
                array("message" => "Info server " . $ipServer . ":\n" .
                                    "Status: " . $status . "\n" .
                                    "Port: " . $port . "\n" .
                                    "IP Server: " . $hostname . "\n" .
                                    "Players Online: " . $onlinePlayers . "\n" .
                                    "Max Players: " . $maxPlayers)
            );
        }
    } elseif (strpos($message, '?mcsbcheck') === 0) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $responses = array(
                array("message" => "Format salah. Gunakan: ?mcsbcheck <IP_server>")
            );
        } else {
            $ipServer = urlencode($parts[1]);
            $mcsbCheckUrl = "https://api.mcsrvstat.us/bedrock/3/" . $ipServer;

            $mcsbCheckResponse = file_get_contents($mcsbCheckUrl);
            $mcsbCheckData = json_decode($mcsbCheckResponse, true);

            $status = isset($mcsbCheckData['online']) && $mcsbCheckData['online'] ? 'online' : 'offline';
            $port = isset($mcsbCheckData['port']) ? $mcsbCheckData['port'] : 'unknown';
            $hostname = isset($mcsbCheckData['hostname']) ? $mcsbCheckData['hostname'] : 'unknown';
            $onlinePlayers = isset($mcsbCheckData['players']['online']) ? $mcsbCheckData['players']['online'] : 'unknown';
            $maxPlayers = isset($mcsbCheckData['players']['max']) ? $mcsbCheckData['players']['max'] : 'unknown';

            $responses = array(
                array("message" => "Info server Bedrock " . $ipServer . ":\n" .
                                    "Status: " . $status . "\n" .
                                    "Port: " . $port . "\n" .
                                    "IP Server: " . $hostname . "\n" .
                                    "Players Online: " . $onlinePlayers . "\n" .
                                    "Max Players: " . $maxPlayers)
            );
        }
    } elseif (strpos($message, '?') === 0) {
        $responses = array(
            array("message" => "Maaf, saya tidak mengerti permintaan Anda. Silakan coba lagi.")
        );
    }

    // set response code - 200 success
    http_response_code(200);

    // send one or multiple replies to AutoResponder
    echo json_encode(array("replies" => $responses));
}

// tell the user json data is incomplete
else {
    // set response code - 400 bad request
    http_response_code(400);
    
    // send error
    echo json_encode(array("replies" => array(
        array("message" => "Error ❌"),
        array("message" => "JSON data is incomplete. Was the request sent by AutoResponder?")
    )));
}
?>

