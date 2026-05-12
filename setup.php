<?php
/**
 * ========================================================================
 * SI PERPUS - MVC GENERATOR / AUTO-INSTALLER (FINAL VERSION)
 * ========================================================================
 * Fitur: Auth, Multi-Role, Relasi Buku-Pengarang (1 Form), Pinjam, Denda
 */

$baseDir = __DIR__;
$files = [];

// ================= 1. KONFIGURASI HTACCESS =================
$files['.htaccess'] = <<<'EOT'
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
EOT;

$files['public/.htaccess'] = <<<'EOT'
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
EOT;

// ================= 2. ENTRY POINT & CORE MVC =================
$files['public/index.php'] = <<<'EOT'
<?php
if(!session_id()) session_start();
require_once '../app/init.php';
$app = new App();
EOT;

$files['app/init.php'] = <<<'EOT'
<?php
require_once 'config/config.php';
require_once 'core/App.php';
require_once 'core/Controller.php';
require_once 'core/Database.php';
require_once 'core/Flasher.php';
EOT;

$files['app/config/config.php'] = <<<'EOT'
<?php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
define('BASE_URL', rtrim($base_url, '/'));

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_si_perpus');
EOT;

$files['app/core/App.php'] = <<<'EOT'
<?php
class App {
    protected $controller = 'Auth';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        $url = $this->parseURL();
        
        if(isset($url[0])) {
            if(file_exists('../app/controllers/' . ucfirst($url[0]) . '.php')) {
                $this->controller = ucfirst($url[0]);
                unset($url[0]);
            }
        }

        if(isset($_SESSION['user_id']) && $this->controller == 'Auth') {
            if(!isset($url[1]) || strtolower($url[1]) !== 'logout') {
                $this->controller = 'Dashboard';
            }
        }

        require_once '../app/controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        if(isset($url[1])) {
            if(method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        if(!empty($url)) {
            $this->params = array_values($url);
        }

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseURL() {
        if(isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            return $url;
        }
        return [];
    }
}
EOT;

$files['app/core/Controller.php'] = <<<'EOT'
<?php
class Controller {
    public function view($view, $data = []) {
        require_once '../app/views/' . $view . '.php';
    }
    public function model($model) {
        require_once '../app/models/' . $model . '.php';
        return new $model;
    }
    public function middlewareAuth($roles = []) {
        if(!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth');
            exit;
        }
        if(!empty($roles) && !in_array($_SESSION['role'], $roles)) {
            die("<div style='padding:20px; text-align:center; font-family:sans-serif;'><h3>403 Access Denied</h3><p>Role Anda tidak memiliki akses ke halaman ini.</p><a href='".BASE_URL."/dashboard'>Kembali</a></div>");
        }
    }
}
EOT;

$files['app/core/Database.php'] = <<<'EOT'
<?php
class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $db_name = DB_NAME;
    private $dbh;
    private $stmt;

    public function __construct() {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name;
        $option = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];
        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $option);
        } catch(PDOException $e) {
            die($e->getMessage());
        }
    }
    public function query($query) { $this->stmt = $this->dbh->prepare($query); }
    public function bind($param, $value, $type = null) {
        if(is_null($type)) {
            switch(true) {
                case is_int($value): $type = PDO::PARAM_INT; break;
                case is_bool($value): $type = PDO::PARAM_BOOL; break;
                case is_null($value): $type = PDO::PARAM_NULL; break;
                default: $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }
    public function execute() { $this->stmt->execute(); }
    public function resultSet() { $this->execute(); return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
    public function single() { $this->execute(); return $this->stmt->fetch(PDO::FETCH_ASSOC); }
    public function rowCount() { return $this->stmt->rowCount(); }
    public function lastInsertId() { return $this->dbh->lastInsertId(); }
}
EOT;

$files['app/core/Flasher.php'] = <<<'EOT'
<?php
class Flasher {
    public static function setFlash($pesan, $aksi, $tipe) {
        $_SESSION['flash'] = ['pesan' => $pesan, 'aksi' => $aksi, 'tipe' => $tipe];
    }
    public static function flash() {
        if(isset($_SESSION['flash'])) {
            echo '<div class="alert alert-'.$_SESSION['flash']['tipe'].' alert-dismissible fade show" role="alert">
                    <strong>'.$_SESSION['flash']['pesan'].'</strong> '.$_SESSION['flash']['aksi'].'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['flash']);
        }
    }
}
EOT;

// ================= 3. MODELS =================
$files['app/models/User_model.php'] = <<<'EOT'
<?php
class User_model {
    private $db;
    public function __construct() { $this->db = new Database; }
    
    public function getUserByEmail($email) {
        $this->db->query("SELECT * FROM users WHERE email = :email");
        $this->db->bind('email', $email);
        return $this->db->single();
    }
    public function countAllUsers() {
        $this->db->query("SELECT COUNT(*) as total FROM users");
        return $this->db->single()['total'];
    }
    public function countByRole($role) {
        $this->db->query("SELECT COUNT(*) as total FROM users WHERE role = :role");
        $this->db->bind('role', $role);
        return $this->db->single()['total'];
    }
    public function getAllUsers() {
        $this->db->query("SELECT * FROM users ORDER BY id DESC");
        return $this->db->resultSet();
    }
    public function getAnggota() {
        $this->db->query("SELECT * FROM users WHERE role = 'anggota' ORDER BY name ASC");
        return $this->db->resultSet();
    }
    public function registerUser($data) {
        $this->db->query("SELECT * FROM users WHERE email = :email");
        $this->db->bind('email', $data['email']);
        $this->db->execute();
        if($this->db->rowCount() > 0) return 0; 

        $count = $this->countAllUsers();
        $role = ($count == 0) ? 'admin' : (($count == 1) ? 'petugas' : 'anggota');

        $this->db->query("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
        $this->db->bind('name', htmlspecialchars($data['name']));
        $this->db->bind('email', htmlspecialchars($data['email']));
        $this->db->bind('password', password_hash($data['password'], PASSWORD_DEFAULT));
        $this->db->bind('role', $role);
        $this->db->execute();
        return $this->db->rowCount();
    }
    public function tambahDataUser($data, $actor_role) {
        $this->db->query("SELECT * FROM users WHERE email = :email");
        $this->db->bind('email', $data['email']);
        $this->db->execute();
        if($this->db->rowCount() > 0) return 0; 

        $role = ($actor_role == 'admin' && isset($data['role'])) ? $data['role'] : 'anggota';

        $this->db->query("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
        $this->db->bind('name', htmlspecialchars($data['name']));
        $this->db->bind('email', htmlspecialchars($data['email']));
        $this->db->bind('password', password_hash($data['password'], PASSWORD_DEFAULT));
        $this->db->bind('role', $role);
        $this->db->execute();
        return $this->db->rowCount();
    }
}
EOT;

$files['app/models/Buku_model.php'] = <<<'EOT'
<?php
class Buku_model {
    private $db;
    public function __construct() { $this->db = new Database; }

    public function getAllBooks() {
        $this->db->query("SELECT books.*, authors.name as author_name FROM books LEFT JOIN authors ON books.author_id = authors.id ORDER BY books.id DESC");
        return $this->db->resultSet();
    }

    public function getBookById($id) {
        $this->db->query("SELECT * FROM books WHERE id = :id");
        $this->db->bind('id', $id);
        return $this->db->single();
    }

    public function countBooks() {
        $this->db->query("SELECT SUM(stock) as total FROM books");
        return $this->db->single()['total'] ?? 0;
    }

    public function tambahBuku($data) {
        // 1. Cek atau Insert Pengarang
        $authorName = trim($data['author_name']);
        $this->db->query("SELECT id FROM authors WHERE name = :name");
        $this->db->bind('name', $authorName);
        $author = $this->db->single();

        if($author) {
            $author_id = $author['id'];
        } else {
            $this->db->query("INSERT INTO authors (name) VALUES (:name)");
            $this->db->bind('name', $authorName);
            $this->db->execute();
            $author_id = $this->db->lastInsertId();
        }

        // 2. Insert Buku
        $this->db->query("INSERT INTO books (title, author_id, year, stock) VALUES (:title, :author_id, :year, :stock)");
        $this->db->bind('title', htmlspecialchars($data['title']));
        $this->db->bind('author_id', $author_id);
        $this->db->bind('year', $data['year']);
        $this->db->bind('stock', $data['stock']);
        $this->db->execute();

        return $this->db->rowCount();
    }

    public function hapusBuku($id) {
        $this->db->query("DELETE FROM books WHERE id = :id");
        $this->db->bind('id', $id);
        $this->db->execute();
        return $this->db->rowCount();
    }
}
EOT;

$files['app/models/Pinjam_model.php'] = <<<'EOT'
<?php
class Pinjam_model {
    private $db;
    public function __construct() { $this->db = new Database; }

    public function getAllLoans() {
        $this->db->query("SELECT loans.*, users.name as user_name, books.title as book_title 
                          FROM loans 
                          JOIN users ON loans.user_id = users.id 
                          JOIN books ON loans.book_id = books.id 
                          ORDER BY loans.status ASC, loans.borrow_date DESC");
        return $this->db->resultSet();
    }

    public function getLoansByUserId($user_id) {
        $this->db->query("SELECT loans.*, books.title as book_title FROM loans JOIN books ON loans.book_id = books.id WHERE user_id = :user_id ORDER BY loans.status ASC");
        $this->db->bind('user_id', $user_id);
        return $this->db->resultSet();
    }

    public function getLoanById($id) {
        $this->db->query("SELECT * FROM loans WHERE id = :id");
        $this->db->bind('id', $id);
        return $this->db->single();
    }

    public function pinjamBuku($data, $actor_id, $actor_role) {
        // Jika anggota pinjam sendiri
        $user_id = ($actor_role == 'anggota') ? $actor_id : $data['user_id'];
        $book_id = $data['book_id'];

        // Cek stok buku
        $this->db->query("SELECT stock FROM books WHERE id = :id");
        $this->db->bind('id', $book_id);
        $book = $this->db->single();
        if($book['stock'] < 1) return -1; // Stok habis

        // Insert Pinjaman (Pinjam hari ini)
        $borrow_date = date('Y-m-d');
        $this->db->query("INSERT INTO loans (user_id, book_id, borrow_date) VALUES (:user_id, :book_id, :borrow_date)");
        $this->db->bind('user_id', $user_id);
        $this->db->bind('book_id', $book_id);
        $this->db->bind('borrow_date', $borrow_date);
        $this->db->execute();

        // Kurangi Stok
        $this->db->query("UPDATE books SET stock = stock - 1 WHERE id = :id");
        $this->db->bind('id', $book_id);
        $this->db->execute();

        return 1;
    }

    public function kembalikanBuku($id, $denda, $book_id) {
        $return_date = date('Y-m-d');
        // Update Loan
        $this->db->query("UPDATE loans SET status = 'dikembalikan', return_date = :return_date, fine = :fine WHERE id = :id");
        $this->db->bind('return_date', $return_date);
        $this->db->bind('fine', $denda);
        $this->db->bind('id', $id);
        $this->db->execute();

        // Tambah Stok
        $this->db->query("UPDATE books SET stock = stock + 1 WHERE id = :id");
        $this->db->bind('id', $book_id);
        $this->db->execute();

        return $this->db->rowCount();
    }

    public function getLaporanDenda() {
        $this->db->query("SELECT loans.*, users.name as user_name, books.title as book_title 
                          FROM loans 
                          JOIN users ON loans.user_id = users.id 
                          JOIN books ON loans.book_id = books.id 
                          WHERE loans.fine > 0");
        return $this->db->resultSet();
    }
}
EOT;

// ================= 4. CONTROLLERS =================
$files['app/controllers/Auth.php'] = <<<'EOT'
<?php
class Auth extends Controller {
    public function index() {
        $data['judul'] = 'Login System';
        $this->view('auth/login', $data);
    }
    public function login() {
        $email = htmlspecialchars($_POST['email']);
        $password = $_POST['password'];

        $user = $this->model('User_model')->getUserByEmail($email);
        if($user) {
            if(password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                header('Location: ' . BASE_URL . '/dashboard');
                exit;
            } else {
                Flasher::setFlash('Password', 'salah!', 'danger');
                header('Location: ' . BASE_URL . '/auth');
                exit;
            }
        } else {
            Flasher::setFlash('Email', 'tidak terdaftar!', 'danger');
            header('Location: ' . BASE_URL . '/auth');
            exit;
        }
    }
    public function register() {
        $data['judul'] = 'Registrasi Akun';
        $this->view('auth/register', $data);
    }
    public function do_register() {
        if($this->model('User_model')->registerUser($_POST) > 0) {
            Flasher::setFlash('Registrasi', 'berhasil! Silakan masuk.', 'success');
            header('Location: ' . BASE_URL . '/auth');
        } else {
            Flasher::setFlash('Registrasi', 'gagal! Email mungkin sudah digunakan.', 'danger');
            header('Location: ' . BASE_URL . '/auth/register');
        }
        exit;
    }
    public function logout() {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/auth');
        exit;
    }
}
EOT;

$files['app/controllers/Dashboard.php'] = <<<'EOT'
<?php
class Dashboard extends Controller {
    public function index() {
        $this->middlewareAuth();
        $data['judul'] = 'Dashboard';
        $data['role'] = $_SESSION['role'];
        $data['name'] = $_SESSION['name'];
        
        if($data['role'] == 'admin') {
            $data['total_anggota'] = $this->model('User_model')->countByRole('anggota');
            $data['total_buku'] = $this->model('Buku_model')->countBooks();
        }
        if($data['role'] == 'anggota') {
            $data['riwayat'] = $this->model('Pinjam_model')->getLoansByUserId($_SESSION['user_id']);
        }

        $this->view('layouts/header', $data);
        $this->view('layouts/sidebar', $data);
        $this->view('layouts/navbar', $data);
        $this->view('dashboard/index', $data);
        $this->view('layouts/footer');
    }
}
EOT;

$files['app/controllers/Users.php'] = <<<'EOT'
<?php
class Users extends Controller {
    public function index() {
        $this->middlewareAuth(['admin', 'petugas']);
        $data['judul'] = 'Manajemen Pengguna';
        $data['role'] = $_SESSION['role'];
        $data['name'] = $_SESSION['name'];
        $data['users'] = $this->model('User_model')->getAllUsers();

        $this->view('layouts/header', $data);
        $this->view('layouts/sidebar', $data);
        $this->view('layouts/navbar', $data);
        $this->view('users/index', $data);
        $this->view('layouts/footer');
    }
    public function tambah() {
        $this->middlewareAuth(['admin', 'petugas']);
        if($this->model('User_model')->tambahDataUser($_POST, $_SESSION['role']) > 0) {
            Flasher::setFlash('Data', 'berhasil ditambahkan', 'success');
        } else {
            Flasher::setFlash('Data', 'gagal ditambahkan', 'danger');
        }
        header('Location: ' . BASE_URL . '/users');
    }
}
EOT;

$files['app/controllers/Buku.php'] = <<<'EOT'
<?php
class Buku extends Controller {
    public function index() {
        $this->middlewareAuth(['admin', 'petugas', 'anggota']); 
        $data['judul'] = 'Katalog & Data Buku';
        $data['role'] = $_SESSION['role'];
        $data['name'] = $_SESSION['name'];
        
        $data['buku'] = $this->model('Buku_model')->getAllBooks();

        $this->view('layouts/header', $data);
        $this->view('layouts/sidebar', $data);
        $this->view('layouts/navbar', $data);
        $this->view('buku/index', $data);
        $this->view('layouts/footer');
    }

    public function tambah() {
        $this->middlewareAuth(['admin', 'petugas']);
        if($this->model('Buku_model')->tambahBuku($_POST) > 0) {
            Flasher::setFlash('Buku', 'berhasil ditambahkan', 'success');
        } else {
            Flasher::setFlash('Buku', 'gagal ditambahkan', 'danger');
        }
        header('Location: ' . BASE_URL . '/buku');
    }

    public function hapus($id) {
        $this->middlewareAuth(['admin', 'petugas']);
        if($this->model('Buku_model')->hapusBuku($id) > 0) {
            Flasher::setFlash('Buku', 'berhasil dihapus', 'success');
        } else {
            Flasher::setFlash('Buku', 'gagal dihapus (mungkin sedang dipinjam)', 'danger');
        }
        header('Location: ' . BASE_URL . '/buku');
    }
}
EOT;

$files['app/controllers/Pinjam.php'] = <<<'EOT'
<?php
class Pinjam extends Controller {
    public function index() {
        $this->middlewareAuth(['admin', 'petugas']);
        $data['judul'] = 'Manajemen Peminjaman';
        $data['role'] = $_SESSION['role'];
        $data['name'] = $_SESSION['name'];
        
        $data['pinjaman'] = $this->model('Pinjam_model')->getAllLoans();
        $data['anggota'] = $this->model('User_model')->getAnggota();
        $data['buku'] = $this->model('Buku_model')->getAllBooks();

        $this->view('layouts/header', $data);
        $this->view('layouts/sidebar', $data);
        $this->view('layouts/navbar', $data);
        $this->view('pinjam/index', $data);
        $this->view('layouts/footer');
    }

    public function tambah() {
        $this->middlewareAuth(['admin', 'petugas', 'anggota']);
        $res = $this->model('Pinjam_model')->pinjamBuku($_POST, $_SESSION['user_id'], $_SESSION['role']);
        if($res == 1) {
            Flasher::setFlash('Buku', 'berhasil dipinjam', 'success');
        } else if($res == -1) {
            Flasher::setFlash('Peminjaman', 'gagal, Stok buku habis!', 'warning');
        } else {
            Flasher::setFlash('Peminjaman', 'gagal', 'danger');
        }
        
        $redirect = ($_SESSION['role'] == 'anggota') ? '/buku' : '/pinjam';
        header('Location: ' . BASE_URL . $redirect);
    }

    public function kembali($id) {
        $this->middlewareAuth(['admin', 'petugas']);
        $loan = $this->model('Pinjam_model')->getLoanById($id);
        
        // Kalkulasi Denda (Telat > 7 hari = Rp 2000/hari)
        $due_date = date('Y-m-d', strtotime($loan['borrow_date'] . ' + 7 days'));
        $today = date('Y-m-d');
        $denda = 0;

        if($today > $due_date) {
            $diff = strtotime($today) - strtotime($due_date);
            $days = floor($diff / (60 * 60 * 24));
            $denda = $days * 2000;
        }

        if($this->model('Pinjam_model')->kembalikanBuku($id, $denda, $loan['book_id']) > 0) {
            $msg = 'Buku dikembalikan. ';
            if($denda > 0) $msg .= "<b>Denda Terlambat: Rp " . number_format($denda,0,',','.') . "</b>";
            Flasher::setFlash('Transaksi', $msg, 'success');
        }
        header('Location: ' . BASE_URL . '/pinjam');
    }
}
EOT;

$files['app/controllers/Denda.php'] = <<<'EOT'
<?php
class Denda extends Controller {
    public function index() {
        $this->middlewareAuth(['admin']);
        $data['judul'] = 'Laporan Denda';
        $data['role'] = $_SESSION['role'];
        $data['name'] = $_SESSION['name'];
        
        $data['laporan'] = $this->model('Pinjam_model')->getLaporanDenda();

        $this->view('layouts/header', $data);
        $this->view('layouts/sidebar', $data);
        $this->view('layouts/navbar', $data);
        $this->view('denda/index', $data);
        $this->view('layouts/footer');
    }
}
EOT;

// ================= 5. VIEWS =================
$files['app/views/auth/login.php'] = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SI Perpus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#f4f6f9;display:flex;align-items:center;justify-content:center;min-height:100vh;}.login-card{width:100%;max-width:400px;padding:2.5rem;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,0.1);background:#fff;border-top:4px solid #0d6efd;margin:15px;}</style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4"><h4><i class="fas fa-book-reader text-primary"></i> SI PERPUS</h4></div>
        <?php Flasher::flash(); ?>
        <form action="<?= BASE_URL; ?>/auth/login" method="POST">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" required placeholder="Email">
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" required placeholder="Password">
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold mb-3">Masuk</button>
        </form>
        <div class="text-center"><small><a href="<?= BASE_URL; ?>/auth/register">Buat Akun Baru</a></small></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
EOT;

$files['app/views/auth/register.php'] = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - SI Perpus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#f4f6f9;display:flex;align-items:center;justify-content:center;min-height:100vh;}.login-card{width:100%;max-width:400px;padding:2.5rem;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,0.1);background:#fff;border-top:4px solid #198754;margin:15px;}</style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4"><h4>Registrasi Akun</h4></div>
        <?php Flasher::flash(); ?>
        <form action="<?= BASE_URL; ?>/auth/do_register" method="POST">
            <div class="mb-3"><input type="text" name="name" class="form-control" required placeholder="Nama Lengkap"></div>
            <div class="mb-3"><input type="email" name="email" class="form-control" required placeholder="Email"></div>
            <div class="mb-3"><input type="password" name="password" class="form-control" required placeholder="Password"></div>
            <button type="submit" class="btn btn-success w-100 fw-bold mb-3">Daftar</button>
        </form>
        <div class="text-center"><small><a href="<?= BASE_URL; ?>/auth">Kembali ke Login</a></small></div>
    </div>
</body>
</html>
EOT;

$files['app/views/layouts/header.php'] = <<<'EOT'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $data['judul']; ?> - SI Perpus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; overflow-x: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { height: 100vh; position: fixed; width: 250px; background-color: #343a40; color: #fff; transition: all 0.3s ease; z-index: 1040; top:0; left:0;}
        .sidebar .brand { padding: 15px; font-size: 1.2rem; font-weight: bold; border-bottom: 1px solid #4b545c; text-align: center; color: #fff; display:block; text-decoration:none;}
        .sidebar a { color: #c2c7d0; text-decoration: none; padding: 12px 20px; display: block; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background-color: #0d6efd; color: #fff; }
        .sidebar .nav-header { padding: 10px 20px; font-size: 0.75rem; text-transform: uppercase; color: #869099; margin-top: 10px; font-weight: bold; }
        .main-content { margin-left: 250px; transition: all 0.3s ease; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - 250px);}
        .navbar-custom { background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 10px 20px; z-index: 1030; position: sticky; top: 0; }
        .content-wrapper { padding: 20px; flex: 1; }
        .card-stat { border-radius: 8px; border: none; box-shadow: 0 2px 5px rgba(0,0,0,.1); overflow: hidden; position: relative; color:#fff;}
        .card-stat .inner { padding: 20px; z-index: 2; position: relative;}
        .card-stat .inner h3 { font-size: 2.2rem; font-weight: bold; margin: 0; }
        .card-stat .icon { font-size: 4rem; color: rgba(0,0,0,0.15); position: absolute; right: 15px; top: 10px; transition: all 0.3s linear; z-index: 1;}
        .card-stat:hover .icon { font-size: 4.5rem; }
        .bg-info { background-color: #17a2b8!important; }
        .bg-success { background-color: #28a745!important; }
        .bg-warning { background-color: #ffc107!important; color: #1f2d3d!important; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1035; }
        @media (max-width: 768px) {
            .sidebar { left: -250px; }
            .sidebar.show { left: 0; }
            .main-content { margin-left: 0; width: 100%; }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
EOT;

$files['app/views/layouts/sidebar.php'] = <<<'EOT'
<div class="sidebar shadow" id="sidebar">
    <a href="<?= BASE_URL; ?>" class="brand"><i class="fas fa-book-reader me-2"></i> SI PERPUS</a>
    <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center" style="border-bottom: 1px solid #4b545c; padding: 0 20px;">
        <div class="image"><img src="https://ui-avatars.com/api/?name=<?= urlencode($data['name']); ?>&background=random" class="rounded-circle" width="40" alt="User"></div>
        <div class="info ms-3 overflow-hidden">
            <div class="d-block text-white fw-bold text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($data['name']); ?></div>
            <span class="badge <?= $data['role']=='admin'?'bg-danger':($data['role']=='petugas'?'bg-primary':'bg-success'); ?> mt-1" style="font-size: 0.7rem;"> <?= ucfirst($data['role']); ?></span>
        </div>
    </div>
    <div class="overflow-auto" style="height: calc(100vh - 150px);">
        <nav class="mt-2 pb-5">
            <ul class="nav flex-column">
                <li><a href="<?= BASE_URL; ?>/dashboard" class="<?= $data['judul']=='Dashboard'?'active':''; ?>"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                
                <?php if($data['role'] == 'admin'): ?>
                <li class="nav-header">Master Data</li>
                <li><a href="<?= BASE_URL; ?>/users" class="<?= $data['judul']=='Manajemen Pengguna'?'active':''; ?>"><i class="fas fa-users me-2"></i> Data Pengguna</a></li>
                <li><a href="<?= BASE_URL; ?>/buku" class="<?= $data['judul']=='Katalog & Data Buku'?'active':''; ?>"><i class="fas fa-book me-2"></i> Data Buku</a></li>
                <li class="nav-header">Transaksi</li>
                <li><a href="<?= BASE_URL; ?>/pinjam" class="<?= $data['judul']=='Manajemen Peminjaman'?'active':''; ?>"><i class="fas fa-hand-holding me-2"></i> Peminjaman</a></li>
                <li><a href="<?= BASE_URL; ?>/denda" class="<?= $data['judul']=='Laporan Denda'?'active':''; ?>"><i class="fas fa-money-bill-wave me-2"></i> Laporan Denda</a></li>
                <?php endif; ?>

                <?php if($data['role'] == 'petugas'): ?>
                <li class="nav-header">Manajemen</li>
                <li><a href="<?= BASE_URL; ?>/users" class="<?= $data['judul']=='Manajemen Pengguna'?'active':''; ?>"><i class="fas fa-id-card me-2"></i> Data Anggota</a></li>
                <li><a href="<?= BASE_URL; ?>/buku" class="<?= $data['judul']=='Katalog & Data Buku'?'active':''; ?>"><i class="fas fa-book me-2"></i> Data Buku</a></li>
                <li class="nav-header">Transaksi</li>
                <li><a href="<?= BASE_URL; ?>/pinjam" class="<?= $data['judul']=='Manajemen Peminjaman'?'active':''; ?>"><i class="fas fa-hand-holding me-2"></i> Peminjaman</a></li>
                <?php endif; ?>

                <?php if($data['role'] == 'anggota'): ?>
                <li class="nav-header">Aktivitas Saya</li>
                <li><a href="<?= BASE_URL; ?>/buku" class="<?= $data['judul']=='Katalog & Data Buku'?'active':''; ?>"><i class="fas fa-book-open me-2"></i> Katalog Buku</a></li>
                <?php endif; ?>

                <li class="nav-header">Sistem</li>
                <li><a href="<?= BASE_URL; ?>/auth/logout" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Keluar Sistem</a></li>
            </ul>
        </nav>
    </div>
</div>
EOT;

$files['app/views/layouts/navbar.php'] = <<<'EOT'
<div class="main-content" id="main-content">
    <nav class="navbar-custom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button class="btn btn-light d-md-none me-2" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5 class="m-0 fw-bold text-dark d-none d-sm-block"><?= $data['judul']; ?></h5>
        </div>
        <div>
            <a href="<?= BASE_URL; ?>/auth/logout" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    <div class="content-wrapper">
EOT;

$files['app/views/dashboard/index.php'] = <<<'EOT'
<div class="container-fluid p-0">
    <div class="alert alert-primary border-0 shadow-sm mb-4">
        <h5 class="m-0"><i class="fas fa-info-circle me-2"></i> Selamat Datang, <b><?= $data['name']; ?></b>!</h5>
    </div>
    
    <?php if($data['role'] == 'admin'): ?>
    <div class="row">
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card-stat bg-info">
                <div class="inner"><h3><?= $data['total_buku']; ?></h3><p>Total Stok Buku</p></div>
                <div class="icon"><i class="fas fa-book"></i></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card-stat bg-success">
                <div class="inner"><h3><?= $data['total_anggota']; ?></h3><p>Total Anggota</p></div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <?php elseif($data['role'] == 'anggota'): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="m-0 fw-bold"><i class="fas fa-history me-2"></i> Riwayat Pinjaman Saya</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0 align-middle">
                    <thead class="table-light"><tr><th class="px-3">Buku</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Status</th><th>Denda</th></tr></thead>
                    <tbody>
                        <?php if(empty($data['riwayat'])): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat peminjaman</td></tr>
                        <?php else: foreach($data['riwayat'] as $r): ?>
                            <tr>
                                <td class="px-3"><?= $r['book_title']; ?></td>
                                <td><?= date('d-m-Y', strtotime($r['borrow_date'])); ?></td>
                                <td><?= $r['return_date'] ? date('d-m-Y', strtotime($r['return_date'])) : '-'; ?></td>
                                <td>
                                    <?php if($r['status'] == 'dipinjam'): ?>
                                        <span class="badge bg-warning text-dark">Sedang Dipinjam</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Dikembalikan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?= $r['fine']>0?'text-danger fw-bold':''; ?>">Rp <?= number_format($r['fine'],0,',','.'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
EOT;

$files['app/views/users/index.php'] = <<<'EOT'
<div class="container-fluid p-0">
    <div class="card p-4 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0"><i class="fas fa-users me-2"></i>Data Pengguna</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="fas fa-plus me-1"></i> Tambah Akun</button>
        </div>
        <?php Flasher::flash(); ?>
        
        <div class="table-responsive mt-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light"><tr><th>No</th><th>Nama Lengkap</th><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                    <?php $no = 1; foreach($data['users'] as $u): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td class="fw-bold"><?= $u['name']; ?></td>
                        <td><?= $u['email']; ?></td>
                        <td><span class="badge <?= $u['role']=='admin'?'bg-danger':($u['role']=='petugas'?'bg-primary':'bg-success'); ?>"><?= ucfirst($u['role']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Buat Akun</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="<?= BASE_URL; ?>/users/tambah" method="POST"><div class="modal-body">
          <div class="mb-3"><label>Nama</label><input type="text" class="form-control" name="name" required></div>
          <div class="mb-3"><label>Email</label><input type="email" class="form-control" name="email" required></div>
          <div class="mb-3"><label>Password</label><input type="password" class="form-control" name="password" required></div>
          <?php if($data['role'] == 'admin'): ?>
          <div class="mb-3"><label>Role</label><select class="form-select" name="role"><option value="anggota">Anggota</option><option value="petugas">Petugas</option><option value="admin">Admin</option></select></div>
          <?php endif; ?>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div></form>
  </div></div>
</div>
EOT;

$files['app/views/buku/index.php'] = <<<'EOT'
<div class="container-fluid p-0">
    <div class="card p-4 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0"><i class="fas fa-book me-2"></i>Katalog Buku</h4>
            <?php if($data['role'] != 'anggota'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuku"><i class="fas fa-plus me-1"></i> Tambah Buku</button>
            <?php endif; ?>
        </div>
        <?php Flasher::flash(); ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light"><tr><th>No</th><th>Judul Buku</th><th>Pengarang</th><th>Tahun</th><th>Stok</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php $no = 1; foreach($data['buku'] as $b): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td class="fw-bold"><?= $b['title']; ?></td>
                        <td><?= $b['author_name']; ?></td>
                        <td><?= $b['year']; ?></td>
                        <td>
                            <?php if($b['stock'] > 0): ?>
                                <span class="badge bg-success"><?= $b['stock']; ?> Tersedia</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Habis</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($data['role'] == 'anggota'): ?>
                                <?php if($b['stock'] > 0): ?>
                                <form action="<?= BASE_URL; ?>/pinjam/tambah" method="POST" class="d-inline">
                                    <input type="hidden" name="book_id" value="<?= $b['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success text-white" onclick="return confirm('Pinjam buku ini?');"><i class="fas fa-hand-holding me-1"></i> Pinjam</button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>Habis</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?= BASE_URL; ?>/buku/hapus/<?= $b['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus buku ini?');"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Buku & Pengarang 1 Form -->
<?php if($data['role'] != 'anggota'): ?>
<div class="modal fade" id="modalBuku" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Input Buku & Pengarang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="<?= BASE_URL; ?>/buku/tambah" method="POST"><div class="modal-body">
          <div class="alert alert-info py-2" style="font-size:0.85rem;"><i class="fas fa-info-circle"></i> Ketik nama pengarang. Jika belum ada, sistem otomatis menyimpannya ke master Pengarang.</div>
          <div class="mb-3"><label>Judul Buku</label><input type="text" class="form-control" name="title" required></div>
          <div class="mb-3"><label>Nama Pengarang</label><input type="text" class="form-control" name="author_name" placeholder="Misal: Tere Liye" required></div>
          <div class="row">
              <div class="col-6 mb-3"><label>Tahun Terbit</label><input type="number" class="form-control" name="year" required></div>
              <div class="col-6 mb-3"><label>Stok Awal</label><input type="number" class="form-control" name="stock" required></div>
          </div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan Buku</button></div></form>
  </div></div>
</div>
<?php endif; ?>
EOT;

$files['app/views/pinjam/index.php'] = <<<'EOT'
<div class="container-fluid p-0">
    <div class="card p-4 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0"><i class="fas fa-hand-holding me-2"></i>Data Peminjaman</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPinjam"><i class="fas fa-plus me-1"></i> Transaksi Baru</button>
        </div>
        <?php Flasher::flash(); ?>
        
        <div class="table-responsive mt-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr><th>No</th><th>Peminjam</th><th>Buku</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach($data['pinjaman'] as $p): 
                        $due_date = date('Y-m-d', strtotime($p['borrow_date'] . ' + 7 days'));
                        $is_late = (date('Y-m-d') > $due_date && $p['status'] == 'dipinjam');
                    ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td class="fw-bold"><?= $p['user_name']; ?></td>
                        <td><?= $p['book_title']; ?></td>
                        <td><?= date('d M Y', strtotime($p['borrow_date'])); ?></td>
                        <td>
                            <span class="<?= $is_late ? 'text-danger fw-bold' : ''; ?>"><?= date('d M Y', strtotime($due_date)); ?></span>
                            <?php if($is_late) echo '<br><small class="text-danger">(Terlambat)</small>'; ?>
                        </td>
                        <td>
                            <?php if($p['status'] == 'dipinjam'): ?>
                                <span class="badge bg-warning text-dark">Dipinjam</span>
                            <?php else: ?>
                                <span class="badge bg-success">Kembali</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['status'] == 'dipinjam'): ?>
                            <a href="<?= BASE_URL; ?>/pinjam/kembali/<?= $p['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Konfirmasi pengembalian buku?');"><i class="fas fa-check"></i> Proses Kembali</a>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>Selesai</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Transaksi Pinjam (Manual oleh Petugas) -->
<div class="modal fade" id="modalPinjam" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Transaksi Peminjaman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="<?= BASE_URL; ?>/pinjam/tambah" method="POST"><div class="modal-body">
          <div class="mb-3"><label>Pilih Anggota</label>
              <select class="form-select" name="user_id" required>
                  <option value="">-- Pilih Anggota --</option>
                  <?php foreach($data['anggota'] as $a): ?>
                  <option value="<?= $a['id']; ?>"><?= $a['name']; ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="mb-3"><label>Pilih Buku</label>
              <select class="form-select" name="book_id" required>
                  <option value="">-- Pilih Buku --</option>
                  <?php foreach($data['buku'] as $b): ?>
                    <?php if($b['stock'] > 0): ?>
                    <option value="<?= $b['id']; ?>"><?= $b['title']; ?> (Stok: <?= $b['stock']; ?>)</option>
                    <?php endif; ?>
                  <?php endforeach; ?>
              </select>
          </div>
          <p class="small text-muted">* Peminjaman dihitung mulai hari ini. Batas waktu pengembalian adalah 7 Hari. Lewat dari itu dikenakan denda Rp 2.000/hari.</p>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Proses Pinjam</button></div></form>
  </div></div>
</div>
EOT;

$files['app/views/denda/index.php'] = <<<'EOT'
<div class="container-fluid p-0">
    <div class="card p-4 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0"><i class="fas fa-money-bill-wave me-2"></i>Laporan Denda Keterlambatan</h4>
        </div>
        
        <div class="table-responsive mt-2">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr><th>No</th><th>Peminjam</th><th>Buku</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Nominal Denda</th></tr>
                </thead>
                <tbody>
                    <?php $no=1; $total=0; foreach($data['laporan'] as $l): $total+=$l['fine']; ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td class="fw-bold"><?= $l['user_name']; ?></td>
                        <td><?= $l['book_title']; ?></td>
                        <td><?= date('d M Y', strtotime($l['borrow_date'])); ?></td>
                        <td><?= date('d M Y', strtotime($l['return_date'])); ?></td>
                        <td class="text-danger fw-bold">Rp <?= number_format($l['fine'],0,',','.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr><td colspan="5" class="text-end fw-bold">TOTAL PENDAPATAN DENDA :</td><td class="fw-bold text-success fs-5">Rp <?= number_format($total,0,',','.'); ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
EOT;

$files['app/views/layouts/footer.php'] = <<<'EOT'
    </div>
    <footer class="bg-white p-3 text-center border-top mt-auto shadow-sm">
        <small class="text-muted">Copyright &copy; <?= date('Y'); ?> <strong>SI Perpustakaan Terpadu</strong>. All rights reserved.</small>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    toggleBtn.addEventListener('click', function() { sidebar.classList.toggle('show'); overlay.classList.toggle('show'); });
    overlay.addEventListener('click', function() { sidebar.classList.remove('show'); overlay.classList.remove('show'); });
</script>
</body>
</html>
EOT;

// ================= 6. ROUTINE GENERATOR =================
echo "<div style='font-family:sans-serif; padding:20px; max-width:800px; margin:0 auto;'>";
echo "<h2><span style='color:blue;'>⚙️</span> Proses Build Final Version...</h2><ul>";

$dirs = ['public/assets/css', 'public/assets/js', 'public/assets/img'];
foreach($dirs as $dir) {
    if(!is_dir($baseDir . '/' . $dir)) {
        mkdir($baseDir . '/' . $dir, 0777, true);
        file_put_contents($baseDir . '/' . $dir . '/index.html', 'Forbidden');
    }
}

foreach($files as $path => $content) {
    $dir = dirname($baseDir . '/' . $path);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    if(file_put_contents($baseDir . '/' . $path, $content) !== false) {
        echo "<li>✅ Berhasil membuat/update: $path</li>";
    }
}
echo "</ul>";

// ================= 7. DATABASE AUTO-SETUP =================
echo "<h2><span style='color:green;'>🗄️</span> Verifikasi Struktur Database...</h2>";
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS db_si_perpus");
    $pdo->exec("USE db_si_perpus");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(100) UNIQUE, password VARCHAR(255), role ENUM('admin', 'petugas', 'anggota') DEFAULT 'anggota', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS authors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS books (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), author_id INT, year INT, stock INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loans (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, book_id INT, borrow_date DATE, return_date DATE NULL, status ENUM('dipinjam', 'dikembalikan') DEFAULT 'dipinjam', fine DECIMAL(10,2) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE)");

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

    echo "<div style='background:#d4edda; border:1px solid #c3e6cb; padding:20px; border-radius:5px; margin-top:20px;'>
            <h3 style='margin-top:0; color:#155724;'>🎉 Instalasi SI Perpus Final Selesai!</h3>
            <p>Semua fitur utama (Multi-Role, Relasi Buku & Pengarang, Peminjaman, Pengurangan Stok, Kalkulasi Denda) telah aktif dan siap digunakan.</p>
            <a href='{$base_url}/public/' style='display:inline-block; background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>🚀 Buka Aplikasi Sekarang</a>
          </div>";
} catch(PDOException $e) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:20px; border-radius:5px;'><b>ERROR Database:</b> " . $e->getMessage(). "</div>";
}
echo "</div>";
?>
