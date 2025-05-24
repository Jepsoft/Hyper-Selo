<?php
require_once("conn.php");

$data = json_decode(file_get_contents("php://input"), true);

$action = $data["action"] ?? null;
$id = $data["id"] ?? null;
$ingredient = $data["ingredient"] ?? null;
$volume = $data["volume"] ?? null;
$msh = $data["msh"] ?? null;

switch ($action) {
    case "insert":
        if (!$ingredient || $volume === null || !$msh) {
            echo json_encode(["status" => "error", "message" => "All fields are required for insert."]);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO kitchen (ingredient, volume, msh) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $ingredient, $volume, $msh);
        $stmt->execute();
        echo json_encode(["status" => "success", "message" => "Ingredient added successfully"]);
        break;

    case "update":
        if (!$id || !$ingredient || $volume === null || !$msh) {
            echo json_encode(["status" => "error", "message" => "All fields are required for update."]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE kitchen SET ingredient = ?, volume = ?, msh = ? WHERE id = ?");
        $stmt->bind_param("sisi", $ingredient, $volume, $msh, $id);
        $stmt->execute();
        echo json_encode(["status" => "success", "message" => "Ingredient updated successfully"]);
        break;

    case "delete":
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID is required for delete."]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM kitchen WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
         $stmt = $conn->prepare("DELETE FROM recipe WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(["status" => "success", "message" => "Ingredient deleted successfully"]);
        break;

    case "fetch_all":
        $result = $conn->query("SELECT * FROM kitchen ORDER BY volume ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();
?>