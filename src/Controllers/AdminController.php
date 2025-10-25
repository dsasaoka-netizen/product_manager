<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // 🛠️ 管理画面ダッシュボード
    public function showDashboard(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SESSION['role'] !== 'admin') {
            return $response->withStatus(403)->write('アクセス権がありません');
        }

        // 未生成サムネイル画像の件数
        $stmt1 = $this->pdo->query("SELECT COUNT(*) FROM product_images WHERE thumbnail IS NULL");
        $missingThumbnailCount = (int)$stmt1->fetchColumn();

        // 画像未登録の商品数（product_images に存在しない product_id を持つ products）
        $stmt2 = $this->pdo->query("
            SELECT COUNT(*) FROM products
            WHERE id NOT IN (
                SELECT DISTINCT product_id FROM product_images
            )
        ");
        $noImageProductCount = (int)$stmt2->fetchColumn();

        // basePath を取得
        $basePath = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();

        // テンプレートに渡す変数を展開
        extract([
            'basePath' => $basePath,
            'missingThumbnailCount' => $missingThumbnailCount,
            'noImageProductCount' => $noImageProductCount,
        ]);

        // テンプレートを読み込んで出力
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/dashboard.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response;
    }

    // 🖼️ サムネイル再生成
    public function regenerateThumbnails(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($_SESSION['role'] !== 'admin') {
            return $response->withStatus(403)->write('アクセス権がありません');
        }

        $message = '';
        if ($request->getMethod() === 'POST') {
            $stmt = $this->pdo->query("SELECT id, file_name FROM product_images WHERE thumbnail IS NULL");
            $images = $stmt->fetchAll();
            $imageDir = dirname(__DIR__, 2) . '/public/images/products/';
            $count = 0;

            foreach ($images as $img) {
                $sourcePath = $imageDir . $img['file_name'];
                $thumbPath = $imageDir . 'thumb_' . $img['file_name'];

                if (file_exists($sourcePath)) {
                    if ($this->createThumbnail($sourcePath, $thumbPath)) {
                        $update = $this->pdo->prepare("UPDATE product_images SET thumbnail = ? WHERE id = ?");
                        $update->execute(['thumb_' . $img['file_name'], $img['id']]);
                        $count++;
                    }
                }
            }

            $message = "サムネイルを再生成しました（{$count} 件）";
        }

        $basePath = \Slim\Routing\RouteContext::fromRequest($request)->getBasePath();

        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/regenerate_thumbnails.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response;
    }

    // 🔧 サムネイル生成処理
    private function createThumbnail(string $sourcePath, string $destPath, int $thumbWidth = 200): bool
    {
        $info = getimagesize($sourcePath);
        if (!$info) return false;

        [$width, $height] = $info;
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($sourcePath); break;
            case 'image/png':  $src = imagecreatefrompng($sourcePath); break;
            case 'image/gif':  $src = imagecreatefromgif($sourcePath); break;
            default: return false;
        }

        $thumbHeight = intval($height * $thumbWidth / $width);
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        switch ($mime) {
            case 'image/jpeg': imagejpeg($thumb, $destPath); break;
            case 'image/png':  imagepng($thumb, $destPath); break;
            case 'image/gif':  imagegif($thumb, $destPath); break;
        }

        imagedestroy($src);
        imagedestroy($thumb);
        return true;
    }
}
