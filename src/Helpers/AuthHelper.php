<?php

namespace App\Helpers;

class AuthHelper {
  public static function requireLogin(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
  }

  public static function checkTimeout(): bool {
    if (!isset($_SESSION['last_activity'])) return true;
    return (time() - $_SESSION['last_activity']) <= 1800; // 30分
  }

  public static function updateActivity(): void {
    $_SESSION['last_activity'] = time();
  }

  public static function forceLogout(): void {
    $_SESSION = [];
    session_destroy();
  }
}
