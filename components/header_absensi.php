<?php
// project-root/components/header.php
// Header HTML untuk semua halaman.
// Termasuk meta tags, link CSS (Tailwind CSS), dan navigasi dasar.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Light gray background */
        }
        .navbar {
            background-color: #1a202c; /* Dark gray for navbar */
        }
        .navbar a {
            color: #cbd5e0; /* Light gray text */
            transition: color 0.3s ease;
        }
        .navbar a:hover {
            color: #ffffff; /* White text on hover */
        }
    </style>
</head>
<!--<body class="min-h-screen flex flex-col">
    <nav class="navbar p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <a href="<?php echo ROOT_URL; ?>" class="text-2xl font-bold"><?php echo APP_NAME; ?></a>
            <div class="space-x-4">
                <a href="<?php echo ROOT_URL; ?>" class="text-lg">Beranda</a>
            </div>
        </div>
    </nav>
    <main class="flex-grow">-->
