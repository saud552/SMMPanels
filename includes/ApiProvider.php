<?php
// includes/ApiProvider.php
class ApiProvider {
    private $api_url;
    private $api_key;

    public function __construct($api_url, $api_key) {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    public function getServices() {
        $post_data = [
            'key' => $this->api_key,
            'action' => 'services'
        ];

        $response = $this->connect($post_data);

        if ($response && is_array($response)) {
            return ['success' => true, 'services' => $response];
        }

        return ['success' => false, 'error' => 'Invalid response'];
    }

    public function createOrder($service_id, $link, $quantity) {
        $post_data = [
            'key' => $this->api_key,
            'action' => 'add',
            'service' => $service_id,
            'link' => $link,
            'quantity' => $quantity
        ];

        $response = $this->connect($post_data);

        if ($response && isset($response['order'])) {
            return ['success' => true, 'order_id' => $response['order']];
        }

        return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
    }

    public function getOrderStatus($order_id) {
        $post_data = [
            'key' => $this->api_key,
            'action' => 'status',
            'order' => $order_id
        ];

        $response = $this->connect($post_data);

        if ($response && isset($response['status'])) {
            return [
                'success' => true,
                'status' => $response['status'],
                'start_count' => $response['start_count'] ?? 0,
                'remains' => $response['remains'] ?? 0,
                'charge' => $response['charge'] ?? 0
            ];
        }

        return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
    }

    private function connect($post) {
        $_post = [];
        foreach ($post as $name => $value) {
            $_post[] = $name . '=' . urlencode($value);
        }

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, join('&', $_post));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }
}
?>