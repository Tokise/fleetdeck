<?php
/**
 * Visit this file once in your browser (http://localhost/fleetdeck/setup.php)
 * to create the `fleetdeck` database, its tables, and some sample data.
 * Safe to run more than once — it only seeds tables that are still empty.
 */
require_once __DIR__ . '/config.php';

$steps = [];

try {
    // Connect without selecting a database yet, so we can create it if missing.
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $steps[] = "Database <code>" . DB_NAME . "</code> ready.";
    $pdo->exec('USE `' . DB_NAME . '`');

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','user') NOT NULL DEFAULT 'user',
        label VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(20) NOT NULL,
        type VARCHAR(30) NOT NULL,
        make VARCHAR(50) NOT NULL,
        model VARCHAR(50) NOT NULL,
        year INT NOT NULL,
        odometer INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'Active'
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle VARCHAR(20),
        requester VARCHAR(100),
        purpose VARCHAR(150),
        pickup VARCHAR(100),
        destination VARCHAR(100),
        date VARCHAR(20),
        status VARCHAR(30) NOT NULL DEFAULT 'Pending'
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        license VARCHAR(50),
        phone VARCHAR(30),
        trips INT NOT NULL DEFAULT 0,
        on_time INT NOT NULL DEFAULT 0,
        safety INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'Active'
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fuel_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle VARCHAR(20),
        date VARCHAR(20),
        liters DECIMAL(8,2) NOT NULL DEFAULT 0,
        price_per_liter DECIMAL(8,2) NOT NULL DEFAULT 0,
        odometer INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        origin VARCHAR(100),
        destination VARCHAR(100),
        distance DECIMAL(6,2) NOT NULL DEFAULT 0,
        duration VARCHAR(30),
        status VARCHAR(30) NOT NULL DEFAULT 'Draft'
    ) ENGINE=InnoDB");

    // Archived records keep the entire original row as JSON in `data_json`,
    // tagged with which table (`source_table`) it came from, so restoring
    // just re-inserts that JSON back into its original table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS archive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(30) NOT NULL,
        source_table VARCHAR(30) NOT NULL,
        data_json TEXT NOT NULL,
        archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        text VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $steps[] = "Tables created (or already existed).";

    // Seed the admin login only if the users table is empty.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, label) VALUES (:u, :p, :r, :l)');
        $stmt->execute(['u' => 'admin', 'p' => password_hash('admin123', PASSWORD_DEFAULT), 'r' => 'admin', 'l' => 'Admin']);
        $steps[] = "Admin login created — username <code>admin</code>, password <code>admin123</code>.";
    } else {
        $steps[] = "Users table already has data — left it alone.";
    }

    // Seed sample fleet data only if the vehicles table is empty, so
    // re-running setup.php never duplicates rows or wipes real data.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO vehicles (plate, type, make, model, year, odometer, status) VALUES
            ('NGA-1001','Truck','Isuzu','ELF 600',2018,39048,'Active'),
            ('NGA-1002','Van','Hyundai','Starex',2019,16434,'Active'),
            ('NGA-1003','Bus','Daewoo','BS106',2022,14395,'Idle'),
            ('NGA-1004','Car','Toyota','Wigo',2018,6905,'Active'),
            ('NGA-1005','Motorcycle','Honda','TMX 155',2019,69237,'Idle'),
            ('NGA-1006','Truck','Isuzu','ELF 600',2022,29062,'Active'),
            ('NGA-1007','Van','Foton','View Transvan',2019,61878,'Idle'),
            ('NGA-1008','Bus','Isuzu','QBus',2024,3851,'Active'),
            ('NGA-1009','Car','Toyota','Wigo',2020,39421,'Active'),
            ('NGA-1010','Motorcycle','Honda','TMX 155',2024,47118,'Active'),
            ('NGA-1011','Truck','Isuzu','ELF 600',2021,15676,'Active'),
            ('NGA-1012','Van','Nissan','NV350',2022,37671,'Active'),
            ('NGA-1013','Bus','Daewoo','BS106',2021,73284,'Active'),
            ('NGA-1014','Car','Toyota','Wigo',2018,75357,'Active'),
            ('NGA-1015','Motorcycle','Yamaha','Mio',2022,28203,'Active'),
            ('NGA-1016','Truck','Isuzu','ELF 600',2018,89673,'Active'),
            ('NGA-1017','Van','Nissan','NV350',2018,33512,'Active'),
            ('NGA-1018','Bus','Isuzu','QBus',2020,62429,'Active'),
            ('NGA-1019','Car','Mitsubishi','Mirage',2019,51520,'Active'),
            ('NGA-1020','Motorcycle','Honda','TMX 155',2023,37993,'Active')");

        $pdo->exec("INSERT INTO drivers (name, license, phone, trips, on_time, safety, status) VALUES
            ('R. Santos','D01-24-799','0917 763 2169',46,90,75,'Suspended'),
            ('M. Cruz','D02-21-350','0917 267 8573',32,78,79,'Active'),
            ('J. Reyes','D03-23-886','0917 894 1916',22,96,67,'Active'),
            ('A. Lopez','D04-16-374','0917 167 4456',66,88,85,'Active'),
            ('D. Garcia','D05-20-611','0917 505 8517',17,78,73,'Active'),
            ('P. Mendoza','D06-21-674','0917 651 5304',55,88,92,'Suspended'),
            ('C. Torres','D07-16-470','0917 324 3266',40,85,70,'Active'),
            ('L. Ramos','D08-23-212','0917 256 3621',58,91,92,'Suspended'),
            ('E. Flores','D09-11-494','0917 490 8668',41,78,65,'Active'),
            ('K. Bautista','D10-20-649','0917 868 5371',57,90,86,'Active'),
            ('N. Villanueva','D11-14-545','0917 261 8433',8,93,81,'Suspended'),
            ('S. Aquino','D12-22-282','0917 619 2743',63,90,84,'Suspended'),
            ('F. Rivera','D13-19-303','0917 256 7126',56,75,98,'Active'),
            ('G. Domingo','D14-19-431','0917 600 1319',15,99,88,'Active'),
            ('H. Castillo','D15-13-159','0917 346 2290',13,93,96,'Active'),
            ('I. Navarro','D16-22-645','0917 884 3060',16,91,95,'Suspended'),
            ('J. Salazar','D17-12-371','0917 640 7932',69,76,77,'Active'),
            ('T. Ocampo','D18-16-787','0917 765 7118',36,98,98,'Idle'),
            ('V. Pascual','D19-11-353','0917 330 2049',29,70,79,'Suspended'),
            ('W. Fernandez','D20-13-107','0917 172 1964',22,72,67,'Active')");

        // Each reservation and fuel entry below references one of the 20
        // vehicle plates above, and each reservation's requester references
        // one of the 20 drivers above, so the seeded records read as a
        // coherent, related fleet rather than disconnected rows.
        $pdo->exec("INSERT INTO reservations (vehicle, requester, purpose, pickup, destination, date, status) VALUES
            ('NGA-1001','Ops — R. Santos','Equipment delivery','Warehouse A','Bulacan Depot','2026-09-08','Completed'),
            ('NGA-1002','Ops — M. Cruz','Supply run','Quezon City Hub','Bulacan Depot','2026-09-05','Cancelled'),
            ('NGA-1003','Ops — J. Reyes','Executive transport','Cavite Plant','Quezon City Hub','2026-09-26','Completed'),
            ('NGA-1004','Ops — A. Lopez','Cargo pickup','Quezon City Hub','Warehouse A','2026-09-04','Dispatched'),
            ('NGA-1005','Ops — D. Garcia','Client delivery','Pasig Branch','Airport Cargo Terminal','2026-09-15','Pending'),
            ('NGA-1006','Ops — P. Mendoza','Site inspection run','Main Depot','Pasig Branch','2026-09-24','Pending'),
            ('NGA-1007','Ops — C. Torres','Emergency dispatch','Quezon City Hub','Airport Cargo Terminal','2026-09-07','Completed'),
            ('NGA-1008','Ops — L. Ramos','Equipment delivery','Warehouse B','Pasig Branch','2026-09-06','Completed'),
            ('NGA-1009','Ops — E. Flores','Emergency dispatch','Quezon City Hub','Warehouse A','2026-09-15','Pending'),
            ('NGA-1010','Ops — K. Bautista','Client delivery','Main Depot','Bulacan Depot','2026-09-27','Pending'),
            ('NGA-1011','Ops — N. Villanueva','Executive transport','Quezon City Hub','Warehouse B','2026-09-14','Completed'),
            ('NGA-1012','Ops — S. Aquino','Client delivery','Quezon City Hub','Pasig Branch','2026-09-29','Approved'),
            ('NGA-1013','Ops — F. Rivera','Equipment delivery','Pasig Branch','Main Depot','2026-09-13','Completed'),
            ('NGA-1014','Ops — G. Domingo','Emergency dispatch','Makati Office','Pasig Branch','2026-09-23','Completed'),
            ('NGA-1015','Ops — H. Castillo','Inter-branch transfer','Warehouse B','Quezon City Hub','2026-09-10','Pending'),
            ('NGA-1016','Ops — I. Navarro','Site inspection run','Airport Cargo Terminal','Bulacan Depot','2026-09-02','Pending'),
            ('NGA-1017','Ops — J. Salazar','Emergency dispatch','Main Depot','Cavite Plant','2026-09-17','Approved'),
            ('NGA-1018','Ops — T. Ocampo','Parts pickup','Main Depot','Bulacan Depot','2026-09-03','Pending'),
            ('NGA-1019','Ops — V. Pascual','Inter-branch transfer','Airport Cargo Terminal','Warehouse A','2026-09-22','Completed'),
            ('NGA-1020','Ops — W. Fernandez','Supply run','Warehouse A','Quezon City Hub','2026-09-19','Pending')");

        $pdo->exec("INSERT INTO fuel_logs (vehicle, date, liters, price_per_liter, odometer) VALUES
            ('NGA-1001','2026-09-20',19.9,64.47,38759),
            ('NGA-1002','2026-09-17',34.0,63.28,16092),
            ('NGA-1003','2026-09-23',33.9,63.30,14328),
            ('NGA-1004','2026-09-22',53.7,63.87,6521),
            ('NGA-1005','2026-09-30',19.4,63.87,68949),
            ('NGA-1006','2026-09-04',19.4,63.14,28927),
            ('NGA-1007','2026-09-05',71.0,65.14,61753),
            ('NGA-1008','2026-09-12',32.1,63.81,3573),
            ('NGA-1009','2026-09-23',33.2,65.45,39087),
            ('NGA-1010','2026-09-17',15.5,64.95,46965),
            ('NGA-1011','2026-09-30',54.8,65.32,15608),
            ('NGA-1012','2026-09-09',21.9,62.82,37388),
            ('NGA-1013','2026-09-05',31.3,64.31,72917),
            ('NGA-1014','2026-09-11',27.2,64.40,75222),
            ('NGA-1015','2026-09-17',44.3,65.22,28177),
            ('NGA-1016','2026-09-03',53.1,64.99,89651),
            ('NGA-1017','2026-09-01',35.0,62.89,33378),
            ('NGA-1018','2026-09-06',59.5,64.16,62211),
            ('NGA-1019','2026-09-18',15.6,62.73,51167),
            ('NGA-1020','2026-09-29',23.9,62.61,37804)");

        $pdo->exec("INSERT INTO routes (name, origin, destination, distance, duration, status) VALUES
            ('Airport Cargo Terminal to Bulacan Depot','Airport Cargo Terminal','Bulacan Depot',8.1,'20 min','Draft'),
            ('Makati Office to Supplier Yard','Makati Office','Supplier Yard',29.2,'62 min','Draft'),
            ('Supplier Yard to Quezon City Hub','Supplier Yard','Quezon City Hub',23.1,'54 min','Draft'),
            ('Supplier Yard to Bulacan Depot','Supplier Yard','Bulacan Depot',28.8,'38 min','Optimized'),
            ('Warehouse B to Quezon City Hub','Warehouse B','Quezon City Hub',28.2,'63 min','Draft'),
            ('Pasig Branch to Main Depot','Pasig Branch','Main Depot',9.0,'33 min','Under Review'),
            ('Quezon City Hub to Makati Office','Quezon City Hub','Makati Office',8.5,'56 min','Draft'),
            ('Pasig Branch to Main Depot (Route 2)','Pasig Branch','Main Depot',28.0,'26 min','Draft'),
            ('Cavite Plant to Supplier Yard','Cavite Plant','Supplier Yard',12.5,'62 min','Draft'),
            ('Quezon City Hub to Main Depot','Quezon City Hub','Main Depot',22.5,'37 min','Under Review'),
            ('Makati Office to Warehouse A','Makati Office','Warehouse A',31.1,'29 min','Under Review'),
            ('Bulacan Depot to Pasig Branch','Bulacan Depot','Pasig Branch',23.0,'65 min','Optimized'),
            ('Supplier Yard to Main Depot','Supplier Yard','Main Depot',7.2,'28 min','Draft'),
            ('Airport Cargo Terminal to Makati Office','Airport Cargo Terminal','Makati Office',5.1,'50 min','Under Review'),
            ('Supplier Yard to Airport Cargo Terminal','Supplier Yard','Airport Cargo Terminal',16.2,'44 min','Draft'),
            ('Pasig Branch to Quezon City Hub','Pasig Branch','Quezon City Hub',11.1,'57 min','Under Review'),
            ('Main Depot to Bulacan Depot','Main Depot','Bulacan Depot',29.9,'46 min','Optimized'),
            ('Quezon City Hub to Supplier Yard','Quezon City Hub','Supplier Yard',16.1,'54 min','Under Review'),
            ('Airport Cargo Terminal to Supplier Yard','Airport Cargo Terminal','Supplier Yard',22.6,'19 min','Optimized'),
            ('Makati Office to Bulacan Depot','Makati Office','Bulacan Depot',12.7,'38 min','Under Review')");

        $steps[] = "20 related vehicles, drivers, reservations, fuel logs and routes seeded.";
    } else {
        $steps[] = "Vehicles table already has data — left your data alone.";
    }

    $ok = true;
} catch (PDOException $e) {
    $ok = false;
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FleetDeck setup</title>
<style>
  body{font-family:system-ui,-apple-system,sans-serif;background:#12151a;color:#e7e9ec;max-width:640px;margin:60px auto;padding:0 20px;line-height:1.6;}
  h1{font-size:22px;}
  .card{background:#191d24;border:1px solid #2a3038;border-radius:8px;padding:20px 24px;margin-top:16px;}
  code{background:#20252d;border:1px solid #2a3038;padding:1px 6px;border-radius:4px;}
  ul{padding-left:20px;}
  li{margin-bottom:6px;}
  a.btn{display:inline-block;margin-top:18px;background:#f2a93b;color:#1a1305;text-decoration:none;font-weight:600;padding:10px 18px;border-radius:6px;}
  .err{color:#e5484d;}
</style>
</head>
<body>
<h1>FleetDeck setup</h1>
<?php if ($ok): ?>
  <div class="card">
    <ul>
      <?php foreach ($steps as $s) echo "<li>$s</li>"; ?>
    </ul>
    <a class="btn" href="index.php">Go to FleetDeck →</a>
  </div>
<?php else: ?>
  <div class="card">
    <p class="err">Setup couldn't connect to MySQL.</p>
    <p>Check that the <b>MySQL</b> service is running in the XAMPP control panel, and that <code>config.php</code> has the right host/user/password (XAMPP defaults to user <code>root</code> with no password).</p>
    <p style="color:#888;font-size:13px;">Details: <?= htmlspecialchars($error) ?></p>
  </div>
<?php endif; ?>
</body>
</html>
