<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $allowed = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif');
        $filename = $_FILES['file']['name'];
        $filetype = $_FILES['file']['type'];
        $filesize = $_FILES['file']['size'];

        // Verifica a extensão do arquivo
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed)) {
            die('Erro: Por favor, selecione um formato de arquivo válido.');
        }

        // Verifica o tamanho do arquivo - 5MB máximo
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            die('Erro: O tamanho do arquivo é maior que o permitido.');
        }

        // Verifica o tipo MIME do arquivo
        if (in_array($filetype, $allowed)) {
            // Salva o arquivo no servidor
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $filepath = $upload_dir . basename($filename);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                echo 'Arquivo enviado com sucesso.<br>';

                // Manipulação da imagem para melhorar a qualidade
                $img = null;
                switch ($filetype) {
                    case 'image/jpeg':
                        $img = imagecreatefromjpeg($filepath);
                        break;
                    case 'image/png':
                        $img = imagecreatefrompng($filepath);
                        break;
                    case 'image/gif':
                        $img = imagecreatefromgif($filepath);
                        break;
                }
                if ($img !== false) {
                    // Converter a imagem para tons de preto e branco (binária)
                    imagefilter($img, IMG_FILTER_GRAYSCALE);

                    // Aumentar o contraste entre preto e branco
                    imagefilter($img, IMG_FILTER_CONTRAST, -150);

                    $processed_filename = 'processed_' . $filename;
                    $processed_filepath = $upload_dir . $processed_filename;
                    switch ($filetype) {
                        case 'image/jpeg':
                            imagejpeg($img, $processed_filepath);
                            break;
                        case 'image/png':
                            imagepng($img, $processed_filepath);
                            break;
                        case 'image/gif':
                            imagegif($img, $processed_filepath);
                            break;
                    }
                    imagedestroy($img);

                    echo 'Imagem processada e salva como: ' . $processed_filename . '<br>';

                    // Executar OCR na imagem processada
                    $output = null;
                    $retval = null;
                    $tesseractPath = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"'; // Caminho com aspas
                    $command = $tesseractPath . " " . escapeshellarg($processed_filepath) . " stdout -l por 2>&1";
                    exec($command, $output, $retval);
                    if ($retval == 0) {
                        echo 'Texto extraído da imagem processada:<br>';
                        echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';

                        // Texto extraído
                        $texto_extraido = implode("\n", $output);

                        // Funções para extrair a data, confronto de times e odds
                        function extrair_data($texto) {
                            preg_match('/\d+\s\w+/', $texto, $matches);
                            return $matches[0] ?? 'Data não encontrada';
                        }

                        function extrair_confronto($texto) {
                            preg_match('/\b[A-Z\s]+\(Fem\)\s-\s[A-Z\s]+\(Fem\)\b/', $texto, $matches);
                            return $matches[0] ?? 'Confronto não encontrado';
                        }

                        function extrair_odds($texto) {
                            preg_match_all('/\b(?<!\()(\d+\.\d{2})(?!\))\b/', $texto, $matches);
                            return $matches[0][0] ?? 'Odds não encontradas';
                        }

                        // Extraindo os dados
                        $data = extrair_data($texto_extraido);
                        $confronto = extrair_confronto($texto_extraido);
                        $odds = extrair_odds($texto_extraido);

                        // Dados de entrada
                        $entries = [
                            ["DATA", "TIPSTER", "CAMPEONATO", "PARTIDA", "MERCADO", "Pré/Live", "UNIDADES", "ODD", "STATUS", "LUCRO/PERDA", "ENTRADA"],
                            [$data, "John Doe", "Premier League", $confronto, "Over/Under", "Pré", 5, $odds, "Win", 4.50, "Entrada 1"],
                            // Adicione mais linhas conforme necessário
                        ];

                        // Cria um novo Spreadsheet
                        $spreadsheet = new Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();

                        // Escreve os dados no arquivo Excel
                        foreach ($entries as $rowIndex => $row) {
                            $colIndex = 'A';
                            foreach ($row as $value) {
                                $sheet->setCellValue($colIndex . ($rowIndex + 1), $value);
                                $colIndex++;
                            }
                        }

                        // Salva o arquivo Excel
                        $writer = new Xlsx($spreadsheet);
                        $excel_filename = 'entradas.xlsx';
                        $writer->save($excel_filename);

                        echo 'Arquivo Excel criado com sucesso!<br>';
                        echo '<a href="' . $excel_filename . '">Baixar arquivo Excel</a>';
                    } else {
                        echo 'Erro ao extrair texto da imagem processada. Código de retorno: ' . $retval . '<br>';
                        echo 'Saída do comando:<br>';
                        echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
                    }
                } else {
                    echo 'Erro ao manipular a imagem.';
                }
            } else {
                echo 'Erro ao enviar o arquivo.';
            }
        } else {
            echo 'Erro: Por favor, tente novamente.';
        }
    } else {
        echo 'Erro: ' . $_FILES['file']['error'];
    }
} else {
    echo 'Método de requisição inválido.';
}
?>