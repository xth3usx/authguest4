<?php
include 'config.php';

ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

session_start();

// Verificar se o código foi recebido
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Verificar se o code_verifier foi armazenado na sessão
    if (isset($_SESSION['code_verifier'])) {
        $code_verifier = $_SESSION['code_verifier'];

        // Trocar o código pelo token de acesso
        $token_data = getAccessToken($code, $code_verifier);

        if ($token_data && isset($token_data['access_token'])) {
            $access_token = $token_data['access_token'];
            $id_token = isset($token_data['id_token']) ? $token_data['id_token'] : null;

            // Validar os tokens usando a chave pública do provedor Gov.br
            if (validateToken($access_token, $id_token)) {
                $userinfo = getUserInfo($access_token);

                if ($userinfo) {
                    echo renderUserProfile($userinfo, $id_token);
                } else {
                    echo renderError("Erro ao obter informações do usuário.");
                }
            } else {
                echo renderError("Falha na validação do token.");
            }
        } else {
            echo renderError("Erro ao trocar o código pelo token.");
        }
    } else {
        // Mensagem de boas-vindas com link
        echo '
            <div style="text-align: center; font-family: Arial, sans-serif; margin-top: 50px;">
                <h2>Bem-vindo(a)!</h2>
                <p style="font-size: 1.2em;">
                    Sua conexão ao Wi-Fi da Universidade Federal Fluminense (UFF) foi realizada com sucesso.
                </p>
                <p>
                    <a href="https://www.uff.br/" target="_blank" style="text-decoration: none; color: #0078d7; font-weight: bold;">
                        Acesse o site da UFF
                    </a>
                </p>
                <p style="color: #666; font-size: 0.9em;">
                    (Nenhum code_verifier encontrado na sessão.)
                </p>
            </div>
        ';
    }
} else {
    echo renderError("Código de autorização não recebido.");
}

// Função para trocar o código pelo token de acesso com retry em caso de falha
function getAccessToken($code, $code_verifier) {
    $token_url = TOKEN_URL;

    $params = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => REDIRECT_URI,
        'client_id' => CLIENT_ID,
        'code_verifier' => $code_verifier
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode(CLIENT_ID . ':' . CLIENT_SECRET)
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $attempts = 0;
    $response = null;
    while ($attempts < 2 && ($response === null || curl_getinfo($ch, CURLINFO_HTTP_CODE) === 0)) {
        $response = curl_exec($ch);
        $attempts++;
        usleep(500000); // Aguarda 0,5 segundo antes de tentar novamente
    }

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return json_decode($response, true);
}

// Função para validar o token
function validateToken($access_token, $id_token) {
    $ch = curl_init('https://sso.staging.acesso.gov.br/jwk');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $jwk = json_decode($response, true);

    return true;  // Implementação simplificada
}

// Função para obter as informações do usuário
function getUserInfo($access_token) {
    $ch = curl_init(USERINFO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Função para exibir o perfil do usuário
function renderUserProfile($userinfo, $id_token = null) {
    $html = '<h2>Perfil do Usuário</h2>';
    $html .= '<p>CPF: ' . htmlspecialchars($userinfo['sub']) . '</p>';
    $html .= '<p>Email: ' . htmlspecialchars($userinfo['email']) . '</p>';

    return $html;
}

// Função para exibir erros
function renderError($message) {
    return '<p style="color: red;">' . htmlspecialchars($message) . '</p>';
}

// Redirecionamento para uma página de erro
if (!isset($_GET['code'])) {
    header("Location: /erro.html");
    exit();
}
