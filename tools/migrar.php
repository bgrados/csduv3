   <?php
   $envFile = __DIR__ . '/../.env';
   if (!file_exists($envFile)) {
       die("Error: No se encontró el archivo .env\n");
   }
   $env = parse_ini_file($envFile);
   $host = $env['DB_HOST'] ?? 'localhost';
   $db   = $env['DB_NAME'];
   $user = $env['DB_USER'];
   $pass = $env['DB_PASS'];

   try {
       $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
       $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       $pdo->exec("CREATE TABLE IF NOT EXISTS csduv3_migraciones (id INT AUTO_INCREMENT PRIMARY KEY, nombre_archivo VARCHAR(255) NOT NULL UNIQUE, fecha_aplicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
       $migrationsDir = __DIR__ . '/../database/migrations';
       $files = glob($migrationsDir . '/*.sql');
       sort($files);
       $aplicadas = 0;
       foreach ($files as $file) {
           $filename = basename($file);
           $stmt = $pdo->prepare("SELECT id FROM csduv3_migraciones WHERE nombre_archivo = ?");
           $stmt->execute([$filename]);
           if ($stmt->rowCount() === 0) {
               echo "Aplicando migración: $filename ...\n";
               $sql = file_get_contents($file);
               try {
                   $pdo->exec($sql);
                   $pdo->prepare("INSERT INTO csduv3_migraciones (nombre_archivo) VALUES (?)")->execute([$filename]);
                   echo "  -> Éxito.\n";
                   $aplicadas++;
               } catch (PDOException $e) {
                   echo "  -> ERROR en $filename: " . $e->getMessage() . "\n";
               }
           }
       }
       echo ($aplicadas === 0) ? "No hay migraciones pendientes. Base de datos actualizada.\n" : "Se aplicaron $aplicadas migración(es).\n";
   } catch (PDOException $e) {
       die("Error de conexión: " . $e->getMessage() . "\n");
   }
   ?>
   EOF



