<?php
// src/Service/AuthService.php

class AuthService {
    private $users;

    public function __construct($config) {
        $this->users = $config['users'];
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password) {
        if (isset($this->users[$username])) {
            if (password_verify($password, $this->users[$username])) {
                $_SESSION['user'] = $username;
                $_SESSION['logged_in'] = true;
                return true;
            }
        }
        return false;
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
    }

    public function isAuthenticated() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }

    public function requireLogin() {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
    }
}
