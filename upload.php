<?php
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
                $img = imagecreatefrompng($filepath);
                if ($img !== false) {
                    // Converter a imagem para tons de preto e branco (binária)
                    imagefilter($img, IMG_FILTER_GRAYSCALE);

                    // Aumentar o contraste entre preto e branco
                    imagefilter($img, IMG_FILTER_CONTRAST, -150);

                    $processed_filename = 'processed_' . $filename;
                    $processed_filepath = $upload_dir . $processed_filename;
                    imagepng($img, $processed_filepath);
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
