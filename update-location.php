<?php
include 'config.php';

if (!isLoggedIn() || !isMitra()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$mitra_id = $input['mitra_id'] ?? $_SESSION['mitra_id'];
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

if ($latitude && $longitude) {
    try {
        // Simpan lokasi ke database
        $sql = "CREATE TABLE IF NOT EXISTS mitra_locations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mitra_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mitra_id) REFERENCES mitra(id) ON DELETE CASCADE
        )";
        $pdo->exec($sql);

        // Insert lokasi terbaru
        $stmt = $pdo->prepare("INSERT INTO mitra_locations (mitra_id, latitude, longitude) VALUES (?, ?, ?)");
        $stmt->execute([$mitra_id, $latitude, $longitude]);

        echo json_encode(['success' => true, 'message' => 'Lokasi berhasil disimpan']);
    } catch (PDOException $e) {
        error_log("Location update error: " . $e->getMessage());
        echo json_encode(['error' => 'Gagal menyimpan lokasi']);
    }
} else {
    echo json_encode(['error' => 'Data lokasi tidak valid']);
}
?>