<?php
class ComfyUIServer {
    private $db;
    private $server_id;
    private $server_data;
    
    public function __construct($db, $server_id) {
        $this->db = $db;
        $this->server_id = $server_id;
        $this->loadServerData();
    }
    
    private function loadServerData() {
        $stmt = $this->db->prepare("SELECT * FROM comfyui_servers WHERE id = ? AND status = 1");
        $stmt->execute([$this->server_id]);
        $this->server_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->server_data) {
            throw new Exception('服务器不存在或已禁用');
        }
    }
    
    public function getServerUrl() {
        return $this->server_data['url'];
    }
    
    public function getApiKey() {
        return $this->server_data['api_key'];
    }
    
    public function logUsage($user_id) {
        $stmt = $this->db->prepare("
            INSERT INTO comfyui_usage_logs (server_id, user_id, created_at)
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$this->server_id, $user_id]);
    }
    
    public function getQueueStatus() {
        $url = rtrim($this->getServerUrl(), '/') . '/queue';
        $headers = [];
        
        if ($this->getApiKey()) {
            $headers[] = 'Authorization: Bearer ' . $this->getApiKey();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('获取队列状态失败');
        }
        
        return json_decode($response, true);
    }
    
    public function submitWorkflow($workflow_data) {
        $url = rtrim($this->getServerUrl(), '/') . '/prompt';
        $headers = ['Content-Type: application/json'];
        
        if ($this->getApiKey()) {
            $headers[] = 'Authorization: Bearer ' . $this->getApiKey();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($workflow_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('提交工作流失败');
        }
        
        return json_decode($response, true);
    }
    
    public function getImage($filename) {
        $url = rtrim($this->getServerUrl(), '/') . '/view?filename=' . urlencode($filename);
        $headers = [];
        
        if ($this->getApiKey()) {
            $headers[] = 'Authorization: Bearer ' . $this->getApiKey();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('获取图片失败');
        }
        
        return $response;
    }
    
    public function getHistory() {
        $url = rtrim($this->getServerUrl(), '/') . '/history';
        $headers = [];
        
        if ($this->getApiKey()) {
            $headers[] = 'Authorization: Bearer ' . $this->getApiKey();
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('获取历史记录失败');
        }
        
        return json_decode($response, true);
    }
} 