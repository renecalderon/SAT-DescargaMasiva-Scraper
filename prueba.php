<?php
declare(strict_types=1);
error_reporting(E_ALL);

require "vendor/autoload.php";

use GuzzleHttp\Client;
use PhpCfdi\CfdiSatScraper\SatHttpGateway;
use PhpCfdi\CfdiSatScraper\SatScraper;
use PhpCfdi\CfdiSatScraper\Sessions\SessionManager;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionManager;
use PhpCfdi\CfdiSatScraper\Sessions\Fiel\FielSessionData;
use PhpCfdi\Credentials\Credential;
use PhpCfdi\Credentials\PrivateKey;
use PhpCfdi\CfdiSatScraper\QueryByFilters;
use PhpCfdi\CfdiSatScraper\ResourceType;
use PhpCfdi\CfdiSatScraper\Filters\Options\ComplementsOption;
use PhpCfdi\CfdiSatScraper\Filters\DownloadType;
use PhpCfdi\CfdiSatScraper\Filters\Options\StatesVoucherOption;
use PhpCfdi\CfdiSatScraper\Filters\Options\RfcOnBehalfOption;
use PhpCfdi\CfdiSatScraper\Filters\Options\RfcOption;

/**
 * @var string $certificate Contenido del certificado
 * @var string $privateKey Contenido de la llave privada
 * @var string $passPhrase Contraseña de la llave privada
 */
$certificateFile = "archivo.cer";
$privateKeyFile = "archivo.key";
$passphrase = 'password';


// crear la credencial
$credential = Credential::openFiles($certificateFile, $privateKeyFile, $passphrase);
if (! $credential->isFiel()) {
    throw new Exception('The certificate and private key is not a FIEL');
}
if (! $credential->certificate()->validOn()) {
    throw new Exception('The certificate and private key is not valid at this moment');
}

$client = new Client([
    'curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1'],
]);

// crear el objeto scraper usando la FIEL
$satScraper = new SatScraper(FielSessionManager::create($credential), new SatHttpGateway($client));

$query = new QueryByFilters(new DateTimeImmutable('2022-01-10'), new DateTimeImmutable('2023-01-10'));
$query
    ->setDownloadType(DownloadType::emitidos())
#    ->setDownloadType(DownloadType::recibidos())                // en lugar de emitidos
#    ->setStateVoucher(StatesVoucherOption::vigentes())          // en lugar de todos
#    ->setRfc(new RfcOption('EKU9003173C9'))                     // de este RFC específico
#    ->setComplement(ComplementsOption::reciboPagoSalarios12())  // que incluya este complemento
#    ->setRfcOnBehalf(new RfcOnBehalfOption('AAA010101AAA'))     // con este RFC A cuenta de terceros
;

$list = $satScraper->listByPeriod($query);

// impresión de cada uno de los metadata
foreach ($list as $cfdi) {
    echo 'UUID: ', $cfdi->uuid(), PHP_EOL;
    echo 'Emisor: ', $cfdi->get('rfcEmisor'), ' - ', $cfdi->get('nombreEmisor'), PHP_EOL;
    echo 'Receptor: ', $cfdi->get('rfcReceptor'), ' - ', $cfdi->get('nombreReceptor'), PHP_EOL;
    echo 'Fecha: ', $cfdi->get('fechaEmision'), PHP_EOL;
    echo 'Tipo: ', $cfdi->get('efectoComprobante'), PHP_EOL;
    echo 'Estado: ', $cfdi->get('estadoComprobante'), PHP_EOL;
}

// descarga de cada uno de los CFDI, reporta los descargados en $downloadedUuids
$downloadedUuids = $satScraper->resourceDownloader(ResourceType::xml(), $list)
    ->setConcurrency(50)                            // cambiar a 50 descargas simultáneas
    ->saveTo('storage/downloads');                 // ejecutar la instrucción de descarga
echo json_encode($downloadedUuids);

?>