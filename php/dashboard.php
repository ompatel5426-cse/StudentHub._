<?php
session_start();

if (!isset($_SESSION["student_id"])) {
    header("Location: login.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard - StudentHub</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f5ff;
            color: #222;
        }

        /* Navbar */
        nav {
            background: white;
            padding: 22px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .logo {
            font-size: 30px;
            font-weight: bold;
            color: #635bff;
        }

        .logout {
            text-decoration: none;
            background: #635bff;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
        }

        /* Dashboard */
        .dashboard {
            padding: 50px 8%;
        }

        .welcome {
            background: linear-gradient(135deg, #6961c9, #9388ff);
            color: white;
            padding: 40px;
            border-radius: 18px;
            margin-bottom: 35px;
        }

        .welcome h1 {
            margin-bottom: 10px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .card {
            background: white;
            padding: 30px 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card h2 {
            color: #635bff;
            margin-bottom: 10px;
        }

        .card p {
            color: #555;
        }

        @media (max-width: 900px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav>
        <div class="logo">StudentHub</div>

        <a href="php/logout.php" class="logout">Logout</a>
    </nav>


    <!-- Dashboard -->
    <section class="dashboard">

        <div class="welcome">

            <h1>
                Welcome, 
                <?= htmlspecialchars($_SESSION["name"]) ?>!
            </h1>

            <p>
                You are successfully logged in to StudentHub.
            </p>

        </div>


        <div class="cards">

            <div class="card">
                <h2>📚</h2>
                <h3>Study Materials</h3>
                <p>Access your study materials.</p>
            </div>

            <div class="card">
                <h2>📝</h2>
                <h3>Assignments</h3>
                <p>View and manage assignments.</p>
            </div>

            <div class="card">
                <h2>📅</h2>
                <h3>Attendance</h3>
                <p>Check your attendance.</p>
            </div>

            <div class="card">
                <h2>📊</h2>
                <h3>Results</h3>
                <p>View your examination results.</p>
            </div>

        </div>

    </section>

</body>
</html>