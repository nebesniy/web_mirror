<?php
// Отключение всех сообщений об ошибках
error_reporting(0);
ini_set("display_errors", 0);
ini_set("log_errors", 0);
ini_set("error_log", __DIR__ . "/logs/php-error.log");

// URL сайта, который хотим проксировать
$base_url = "https://example.com";

// Подключение автозагрузчика
require __DIR__ . '/vendor/autoload.php';

use Curl\Curl;

// Обработка запроса к robots.txt
if ($_SERVER["REQUEST_URI"] == "/robots.txt") {
	// Установка типа контента для файла robots.txt
	header("Content-Type: text/plain; charset=UTF-8");

	// Содержимое файла robots.txt
	echo "User-agent: *
Disallow: /

User-agent: Twitterbot
Allow: /

User-agent: facebookexternalhit
Allow: /";

	// Завершение выполнения скрипта
	die();
}

// Замер времени начала выполнения (если нужно для логирования)
$start = microtime(true);

// Инициализация библиотеки Curl
$curl = new Curl();
$curl->setUserAgent("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15");

// Установка куки из текущего запроса
$curl->setCookies($_COOKIE);

// Настройка параметров Curl
$curl->setOpt(CURLOPT_TIMEOUT, 5); // Таймаут в секундах
$curl->setOpt(CURLOPT_NOBODY, 0); // Загружать тело ответа
$curl->setOpt(CURLOPT_HEADER, 0); // Не возвращать заголовки ответа
$curl->setOpt(CURLOPT_FOLLOWLOCATION, 1); // Следовать за редиректами
$curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0); // Не проверять SSL-сертификат
$curl->setOpt(CURLOPT_RETURNTRANSFER, 1); // Возвращать результат как строку
$curl->setOpt(CURLOPT_SSL_VERIFYHOST, 0); // Не проверять хост SSL

// Выполнение запроса на целевой URL
$curl->get($base_url . $_SERVER["REQUEST_URI"]);

// Получение содержимого ответа
$file = $curl->response;

// Установка новых cookies на основе ответа
foreach ($curl->responseCookies as $cookiekey => $cookievalue) {
	setcookie($cookiekey, $cookievalue);
}

// Проверка типа контента ответа и замена ссылок, если это HTML или CSS
if (
	strpos($curl->responseHeaders['Content-Type'], 'text/html') !== false ||
	strpos($curl->responseHeaders['Content-Type'], 'text/css') !== false
) {
	$file = str_replace(
		$base_url,
		$_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["SERVER_NAME"],
		$file
	);
}

// Установка заголовков для клиентского браузера
header("Content-Type: " . $curl->responseHeaders['Content-Type']);
header("Content-Length: " . $curl->responseHeaders['Content-Length']);

// Логирование ошибок, если запрос завершился неудачно
if ($curl->error) {
	$error_desc = json_encode($curl->errorCode) . "|" . json_encode($curl->errorMessage);
	file_put_contents(__DIR__ . "/logs/curl_error.log", $error_desc . PHP_EOL, FILE_APPEND);
} else {
	// Вывод содержимого ответа
	echo $file;
}

// Завершение работы Curl
$curl->close();
exit;
?>