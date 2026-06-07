<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>exclusion-inclusion</title>

<!-- External CSS -->
<link rel="icon" type="image/x-icon" href="assets/favicon.ico?v=2">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="assets/css/header.css">
<link rel="stylesheet" href="assets/css/main.css">

<style>
/* Sticky note style */
.sticky-note {
    width: 400px;
    height: 200px;
    background: #fffa65;
    color: #333;
    font-size: 32px;
    font-weight: bold;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    border: 5px dashed #f2c94c;
    box-shadow: 5px 5px 15px rgba(0,0,0,0.3);
    margin: 100px auto;
    transform: rotate(-3deg);
    font-family: 'Comic Sans MS', Cursive, sans-serif;
}
</style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="sticky-note">
              server room lang po, sir                                                                                                    
    </div>
</div>

<script>
// Clock
function updateClock() {
    const clock = document.getElementById('clock');
    if (!clock) return;

    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);

    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2,'0');
    const seconds = String(now.getSeconds()).padStart(2,'0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;

    clock.textContent = `${dateStr} • ${String(hours).padStart(2,'0')}:${minutes}:${seconds} ${ampm}`;
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggle = document.getElementById('sidebarToggle');
if (toggle) {
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
}
</script>

</body>
</html>
