<?php

namespace App\Controllers;

use App\Helpers\AuthHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class AdminController
{
    private \PDO $pdo;
    private $view;

    public function __construct(\PDO $pdo, $view)
    {
        $this->pdo = $pdo;
        $this->view = $view;
    }

    // 🔐 共通セッションチェック（管理者専用）
    private function checkAdmin(Response $response): ?Response {
        if (!AuthHelper::requireLogin() || !AuthHelper::checkTimeout()) {
            AuthHelper::forceLogout();
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        AuthHelper::updateActivity();
        if ($_SESSION['role'] !== 'admin') {
            $response->getBody()->write('管理者のみアクセス可能です');
            return $response->withStatus(403);
        }
        return null;
    }

    // 🛠️ 管理画面ダッシュボード
    public function showDashboard(Request $request, Response $response): Response
    {
        if ($redirect = $this->checkAdmin($response)) return $redirect;

        $stmt1 = $this->pdo->query("SELECT COUNT(*) FROM product_images WHERE thumbnail IS NULL");
        $missingThumbnailCount = (int)$stmt1->fetchColumn();

        $stmt2 = $this->pdo->query("
            SELECT COUNT(*) FROM products
            WHERE id NOT IN (
                SELECT DISTINCT product_id FROM product_images
            )
        ");
        $noImageProductCount = (int)$stmt2->fetchColumn();

        $basePath = RouteContext::fromRequest($request)->getBasePath();

        return $this->view->render($response, 'admin/admin_dashboard.twig', [
            'basePath' => $basePath,
            'missingThumbnailCount' => $missingThumbnailCount,
            'noImageProductCount' => $noImageProductCount,
            'session' => $_SESSION
        ]);
    }

    // 🖼️ サムネイル再生成
    public function regenerateThumbnails(Request $request, Response $response): Response
    {
        if ($redirect = $this->checkAdmin($response)) return $redirect;

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

        $basePath = RouteContext::fromRequest($request)->getBasePath();

        return $this->view->render($response, 'admin/regenerate_thumbnails.twig', [
            'basePath' => $basePath,
            'message' => $message,
            'session' => $_SESSION
        ]);
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
