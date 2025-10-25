<?php
namespace App\Models;

use PDO;

class CategoryModel {
  private PDO $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function getSortedTree(): array {
    $stmt = $this->pdo->query("SELECT id, name, parent_id FROM categories ORDER BY sort_order ASC");
    $categories = $stmt->fetchAll();
    return $this->buildTree($categories);
  }

  private function buildTree(array $categories, $parentId = null, $depth = 0): array {
    $tree = [];
    foreach ($categories as $cat) {
      if ($cat['parent_id'] === $parentId) {
        $cat['depth'] = $depth;
        $tree[] = $cat;
        $tree = array_merge($tree, $this->buildTree($categories, $cat['id'], $depth + 1));
      }
    }
    return $tree;
  }
}
