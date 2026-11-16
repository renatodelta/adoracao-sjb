<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

function getFilePath($date) {
    global $dataDir;
    $cleanDate = preg_replace('/[^0-9\-]/', '', $date);
    if (empty($cleanDate)) {
        $cleanDate = date('Y-m-d');
    }
    return $dataDir . '/cerco_sjb_' . $cleanDate . '.json';
}

function getCercoJericoSlots() {
    $slots = [];
    for ($h = 0; $h < 24; $h++) {
        $time = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
        $notes = ($h < 6) ? 'Madrugada' : '';
        $slots[] = ["time" => $time, "adorers" => [], "notes" => $notes];
    }
    return $slots;
}

$defaultQuoteText = "“Eu e minha casa serviremos ao Senhor.” (Js 24,15)\n“Buscai o Senhor enquanto Ele se deixa encontrar.” (Is 55,6)";
$defaultQuoteAuthor = "Cerco de Jericó · 15 a 22 de Novembro · Paróquia São José";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $filePath = getFilePath($date);

    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        if (!isset($data['quote_text'])) {
            $data['quote_text'] = $defaultQuoteText;
            $data['quote_author'] = $defaultQuoteAuthor;
        }
    } else {
        $data = [
            "date" => $date,
            "title" => "Cerco de Jericó — Adoração Dia e Noite",
            "community" => "Paróquia São José — São José do Barreiro",
            "quote_text" => $defaultQuoteText,
            "quote_author" => $defaultQuoteAuthor,
            "slots" => getCercoJericoSlots(),
            "last_updated" => date('c')
        ];
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

if ($method === 'POST') {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (!$input) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? $input['action'] : '';
    $date = isset($input['date']) ? $input['date'] : date('Y-m-d');
    $filePath = getFilePath($date);

    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        if (!isset($data['quote_text'])) {
            $data['quote_text'] = $defaultQuoteText;
            $data['quote_author'] = $defaultQuoteAuthor;
        }
    } else {
        $data = [
            "date" => $date,
            "title" => "Cerco de Jericó — Adoração Dia e Noite",
            "community" => "Paróquia São José — São José do Barreiro",
            "quote_text" => $defaultQuoteText,
            "quote_author" => $defaultQuoteAuthor,
            "slots" => getCercoJericoSlots(),
            "last_updated" => date('c')
        ];
    }

    if ($action === 'save_quote') {
        $data['quote_text'] = trim(isset($input['quote_text']) ? $input['quote_text'] : '');
        $data['quote_author'] = trim(isset($input['quote_author']) ? $input['quote_author'] : '');
        $data['last_updated'] = date('c');
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(["status" => "success", "message" => "Frase atualizada com sucesso!", "data" => $data]);
        exit;
    }

    if ($action === 'book') {
        $time = isset($input['time']) ? $input['time'] : '';
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $phone = trim(isset($input['phone']) ? $input['phone'] : '');
        $intention = trim(isset($input['intention']) ? $input['intention'] : '');

        if (empty($name) || empty($time)) {
            echo json_encode(["status" => "error", "message" => "Nome e horário são obrigatórios."]);
            exit;
        }

        $updated = false;
        foreach ($data['slots'] as &$slot) {
            if ($slot['time'] === $time) {
                if (!isset($slot['adorers'])) {
                    $slot['adorers'] = [];
                }
                $slot['adorers'][] = [
                    "name" => $name,
                    "phone" => $phone,
                    "intention" => $intention,
                    "created_at" => date('c')
                ];
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $data['last_updated'] = date('c');
            file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(["status" => "success", "message" => "Horário agendado com sucesso!", "data" => $data]);
        } else {
            echo json_encode(["status" => "error", "message" => "Horário não encontrado."]);
        }
        exit;
    }

    if ($action === 'remove') {
        $time = isset($input['time']) ? $input['time'] : '';
        $index = isset($input['index']) ? intval($input['index']) : 0;

        foreach ($data['slots'] as &$slot) {
            if ($slot['time'] === $time) {
                if (isset($slot['adorers'][$index])) {
                    array_splice($slot['adorers'], $index, 1);
                    break;
                }
            }
        }

        $data['last_updated'] = date('c');
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(["status" => "success", "message" => "Adorador removido com sucesso!", "data" => $data]);
        exit;
    }

    if ($action === 'reset_day') {
        $data['slots'] = getCercoJericoSlots();
        $data['last_updated'] = date('c');
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(["status" => "success", "message" => "Horários reiniciados para o padrão!", "data" => $data]);
        exit;
    }

    if ($action === 'add_general_intention') {
        $name = trim(isset($input['name']) ? $input['name'] : 'Anônimo');
        $intention = trim(isset($input['intention']) ? $input['intention'] : '');

        if (empty($intention)) {
            echo json_encode(["status" => "error", "message" => "O pedido de oração não pode estar vazio."]);
            exit;
        }

        if (!isset($data['general_intentions']) || !is_array($data['general_intentions'])) {
            $data['general_intentions'] = [];
        }

        $data['general_intentions'][] = [
            "name" => empty($name) ? "Anônimo" : $name,
            "intention" => $intention,
            "created_at" => date('c')
        ];

        $data['last_updated'] = date('c');
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(["status" => "success", "message" => "Pedido de oração adicionado com sucesso!", "data" => $data]);
        exit;
    }

    if ($action === 'save_all') {
        if (isset($input['slots']) && is_array($input['slots'])) {
            $data['slots'] = $input['slots'];
            $data['last_updated'] = date('c');
            file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(["status" => "success", "message" => "Escala salva com sucesso!", "data" => $data]);
        } else {
            echo json_encode(["status" => "error", "message" => "Dados inválidos."]);
        }
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Ação desconhecida."]);
    exit;
}
