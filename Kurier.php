<?php

declare(strict_types=1);

class Kurier
{
    private string $url;
    private array $params;
    private string $apiKey;
    private array $errorMessages = [];

    /**
     * @param string $url
     * @param array $params
     */
    public function __construct(string $url, array $params)
    {
        $this->url = $url;
        $this->params = $params;
        $this->apiKey = $params['api_key'];
    }

    /**
     * @param array $order
     * @param array $params
     * @return string
     */
    public function newPackage(array $order, array $params): string
    {
        $apiResponse = $this->callApi($this->prepareNewPackagePayload($params, $order));

        if (isset($apiResponse['Shipment']['TrackingNumber'])) {
            return $apiResponse['Shipment']['TrackingNumber'];
        }

        $this->endWithCode(400, json_encode($apiResponse));
    }

    /**
     * @param string $trackingNumber
     * @return void
     */
    public function packagePDF(string $trackingNumber): void
    {
        $apiResponse = $this->callApi($this->preparePackagePDFPayload($trackingNumber));

        if (isset($apiResponse['Shipment']['LabelImage'])) {
            $filename = $this->processLabel($apiResponse['Shipment']['LabelImage'], $trackingNumber);
            $this->endWithCode(200, sprintf('Label %s was downloaded to your device', $filename));
        }

        $this->endWithCode(400, json_encode($apiResponse));
    }

    /**
     * @param int $code
     * @param string $message
     * @return void
     */
    private function endWithCode(int $code, string $message): void
    {
        http_response_code($code);
        $message .= count($this->errorMessages) !== 0 ? sprintf(', Errors: %s', json_encode(array_unique($this->errorMessages))) : '';
        die(sprintf('%s: %s', $code, $message));
    }


    /**
     * @param array $data
     * @return array
     */
    private function callApi(array $data): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/json",
            ]
        ]);

        $responseRaw = curl_exec($ch);
        curl_close($ch);

        if (is_string($responseRaw)) {
            $responseArray = json_decode($responseRaw, true);
            if (isset($responseArray['ErrorLevel']) && $responseArray['ErrorLevel'] !== 0) {
                $this->errorMessages[] = sprintf('ErrorLevel: %s [ %s ]', $responseArray['ErrorLevel'], $responseArray['Error']);
            }
            return $responseArray;
        }
        $this->endWithCode(502, 'Unexpected response from courier API');
    }

    /**
     * @param string $filename
     * @param mixed $label
     * @return void
     */
    private function saveFile(string $filename, mixed $label): void
    {
        if (php_sapi_name() === 'cli') {
            file_put_contents($filename, base64_decode($label));
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            echo base64_decode($label);
        }
    }

    /**
     * @param array $params
     * @param array $order
     * @return array
     *
     * @ToDo fill up rest of parameters
     */
    private function prepareNewPackagePayload(array $params, array $order): array
    {
        $command = 'OrderShipment';

        return [
            'Apikey' => $this->apiKey,
            'Command' => $command,
            'Shipment' => [
                'LabelFormat' => $params['label_format'],
                'ShipperReference' => '',
                'OrderReference' => '',
                'OrderDate' => '',
                'DisplayId' => '',
                'InvoiceNumber' => '',
                'Service' => $params['service'],
                'Weight' => '',
                'WeightUnit' => '',
                'Length' => '',
                'Width' => '',
                'Height' => '',
                'DimUnit' => '',
                'Value' => '',
                'ShippingValue' => '',
                'Currency' => '',
                'CustomsDuty' => '',
                'Description' => '',
                'DeclarationType' => '',
                'DangerousGoods' => '',
                'ExportCarrierName' => '',
                'ExportAwb' => '',
                'ConsignorAddress' => [
                    'Name' => $order['sender_fullname'],
                    'Company' => $order['sender_company'],
                    'AddressLine1' => $order['sender_address'],
                    'AddressLine2' => '',
                    'AddressLine3' => '',
                    'City' => $order['sender_city'],
                    'State' => '',
                    'Zip' => $order['sender_postalcode'],
                    'Country' => '',
                    'Phone' => $order['sender_phone'],
                    'Email' => $order['sender_email'],
                    'Vat' => '',
                    'Eori' => '',
                    'NlVat' => '',
                    'EuEori' => '',
                    'Ioss' => '',
                ],
                'ConsigneeAddress' => [
                    'Name' => $order['delivery_fullname'],
                    'Company' => $order['delivery_company'],
                    'AddressLine1' => $order['delivery_address'],
                    'AddressLine2' => '',
                    'AddressLine3' => '',
                    'City' => $order['delivery_city'],
                    'State' => '',
                    'Zip' => $order['delivery_postalcode'],
                    'Country' => $order['delivery_country'],
                    'Phone' => $order['delivery_phone'],
                    'Email' => $order['delivery_email'],
                    'Vat' => '',
                    'PudoLocationId' => '',
                ],
                'Products' => [
                    [
                        'Description' => '',
                        'Sku' => '',
                        'HsCode' => '',
                        'OriginCountry' => '',
                        'ImgUrl' => '',
                        'PurchaseUrl' => '',
                        'Quantity' => '',
                        'Value' => '',
                        'Weight' => '',
                        'DaysForReturn' => '',
                        'NonReturnable' => '',
                    ]
                ]

            ]
        ];
    }

    /**
     * @param string $trackingNumber
     * @return array
     *
     * @ToDo fill up rest of parameters
     */
    private function preparePackagePDFPayload(string $trackingNumber): array
    {
        $command = 'GetShipmentLabel';

        return [
            'Apikey' => $this->apiKey,
            'Command' => $command,
            'Shipment' => [
                'LabelFormat' => $this->params['label_format'],
                'TrackingNumber' => $trackingNumber,
                'ShipperReference' => '',
            ]
        ];
    }

    /**
     * @param string $labelImage
     * @param string $trackingNumber
     * @return string
     */
    private function processLabel(string $labelImage, string $trackingNumber): string
    {
        $label = $labelImage;
        $filename = sprintf('%s.pdf', $trackingNumber);
        $this->saveFile($filename, $label);
        return $filename;
    }

}
