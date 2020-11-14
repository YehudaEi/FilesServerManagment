<?php

define('DONT_SHOW', array('.', '..', ".htaccess", "readme.md"));
define('DS', DIRECTORY_SEPARATOR);
define('BASE_PATH', __DIR__ . DS . "data" . DS);
define('BASE_PATH_SF', __DIR__ . DS . "Secret-Folder" . DS);
define('BASE_URL', ($_SERVER['REQUEST_SCHEME'] ?? ($_SERVER['HTTPS'] == "on" ? "https" : "http")) . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/");

session_name('FileServerMng');
session_start();

if(file_exists(substr(BASE_PATH, 0, -1)) && !is_dir(substr(BASE_PATH, 0, -1))) die("<h1 style='color:red'>Fatal Error!<h1>");
if(!is_dir(BASE_PATH)) mkdir(BASE_PATH);
if(!is_dir(BASE_PATH_SF)) mkdir(BASE_PATH_SF);
if(!file_exists(BASE_PATH . '.htaccess')) file_put_contents(BASE_PATH . '.htaccess', 'deny from all');
if(!file_exists(BASE_PATH_SF . '.htaccess')) file_put_contents(BASE_PATH_SF . '.htaccess', 'deny from all');
if(!file_exists(__DIR__ . DS . '.htaccess')) file_put_contents(__DIR__ . DS . '.htaccess', "DirectoryIndex SysManager.php\nRewriteEngine on\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.*)$ ./SysManager.php?file=$1 [NC,L,QSA]");

$users =  array(
    'admin' => password_hash('admin@123', PASSWORD_DEFAULT),
);
$isLogged = false;

function formatSizeUnits($bytes){
    if ($bytes >= 1073741824)
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576)
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024)
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    elseif ($bytes > 1)
        $bytes = $bytes . ' bytes';
    elseif ($bytes == 1)
        $bytes = $bytes . ' byte';
    else
        $bytes = '0 bytes';
    
    return $bytes;
}
function getAbsolutePath($path) {
    $path = str_replace(array('/', '\\'), DS, $path);
    $parts = array_filter(explode(DS, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) continue;
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DS, $absolutes);
}
function cleanPath($path){
    $path = trim($path);
    $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    $path =  getAbsolutePath($path);
    if ($path == '..') {
        $path = '';
    }
    return str_replace('\\', '/', $path);
}
function del($path){
    if (is_link($path)) {
        return unlink($path);
    } elseif (is_dir($path)) {
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!del($path . '/' . $file)) {
                        $ok = false;
                    }
                }
            }
        }
        return ($ok) ? rmdir($path) : false;
    } elseif (is_file($path)) {
        return unlink($path);
    }
    return false;
}
function getFakePath($path, $secretMode){
    $res = cleanPath(substr($path, strlen($secretMode ? BASE_PATH_SF : BASE_PATH)));
    return empty($res) ? "/" : $res;
}
function printHeader($path, $secretMode = false){
    echo "<p>Hello <b>" . htmlspecialchars($_SESSION['FileServerMngUser']['logged']) . "</b>. 
        [<a href='" . BASE_URL . "'>home</a>] 
        [<a href='?logout'>logout</a>] 
        [<a href='" . BASE_URL . $path . "?act=upload'>upload</a>] 
        [<a href='" . BASE_URL . ($secretMode ? "?act=public'>public files</a>] " : "?act=secret'>secret files</a>] ") . "
        [<a href='" . BASE_URL . $path . "?act=folder'>create folder</a>] 
        <b>Secret Mode:</b> " . ($secretMode ? "ON" : "OFF") . "
    </p>
    ";
}
function printFilesTable($path, $isLogged, $secretMode = false){
?>

<html>
    <head>
        <title>Yehuda's Files Server 😉 | <?php echo empty($path) ? "home" : $path; ?></title>
        <script>console.log("%c‎E‎v‎e‎r‎y‎t‎h‎i‎n‎g‎ ‎i‎s‎ ‎p‎r‎otected‎,‎ ‎y‎o‎u‎ ‎w‎i‎l‎l‎ f‎i‎n‎d‎ ‎n‎o‎t‎h‎i‎n‎g‎ ‎h‎e‎r‎e‎ ‎😁‎", "color:red;font-size:30px;font-weight:bold;")</script>
    </head>
    <body>
    <?php if($isLogged) printHeader($path, $secretMode); ?>
        <table border='1'>
            <tr>
                <th>Name</th>
                <th>Last modified</th>
                <th>Size</th>
            <?php if($isLogged){ ?>

                <th>ReName</th>
                <th>Delete</th>
            <?php } ?>

            </tr>
<?php

if(!empty($path)){
    ?>
        <tr>
            <td><a href='<?php echo BASE_URL;?>'>Go Back</a></td>
            <td>-</td>
            <td>-</td>
        <?php if($isLogged){ ?>

            <th>-</th>
            <th>-</th>
        <?php } ?>

        </tr>
    <?php
}
elseif(!$isLogged){
    ?>
        <tr>
            <td><a href='<?php echo BASE_URL;?>?login'>Login</a></td>
            <td>-</td>
            <td>-</td>
        </tr>
    <?php
}

foreach (scandir(($secretMode ? BASE_PATH_SF : BASE_PATH) . $path) as $object){
    $object = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $path . DS .  $object;
    $name = basename($object);
    
    if(in_array($name, DONT_SHOW) && (!$isLogged || $name != 'readme.md')) continue;

    $htmlName = htmlspecialchars($name);
    $link = BASE_URL . getFakePath($object, $secretMode);
    $lastMod = date('d/m/Y H:i', filemtime($object));
    
    if(is_dir($object))
        $size = "<i style='margin-left:4px;'>folder</i>";
    else
        $size = formatSizeUnits(filesize($object));
    
?>
            <tr>
                <td><a href='<?php echo $link; ?>'><?php echo $htmlName; ?></a></td>
                <td><?php echo $lastMod; ?></td>
                <td><?php echo $size; ?></td>
            <?php if($isLogged) { ?>
            
                <th><a href='<?php echo $link; ?>?act=rename'>ReName this file / folder</a></th>
                <th><a href='<?php echo $link; ?>?act=delete'>Delete this file / folder</a></th>
            <?php } ?>
            
            </tr>
<?php } ?>
        </table>
<?php 

if(file_exists(($secretMode ? BASE_PATH_SF : BASE_PATH) . $path . DS . 'readme.md') && file_exists('md-parser.php')){
    echo "<hr><h1>ReadMe:</h1>";
    include 'md-parser.php'; //Download from https://github.com/erusev/parsedown
    
    $Parsedown = new Parsedown();
    $Parsedown->setSafeMode(true);
    
    echo $Parsedown->text(file_get_contents(($secretMode ? BASE_PATH_SF : BASE_PATH) . $path . DS . 'readme.md'));
    
    echo "<hr>";
}
?>

    </body>
</html>
<?php
}

$file = cleanPath($_GET['file'] ?? "");
if(empty($file)) $file = "";

if(isset($_GET['logout'])){ 
    unset($_SESSION['FileServerMngUser']['logged']); 
    header('location: ' . BASE_URL);
}
if (isset($_SESSION['FileServerMngUser']['logged'], $users[$_SESSION['FileServerMngUser']['logged']])){
    $isLogged = true;
}
if(isset($_GET['login'])){ 
    if (isset($_POST['user'], $_POST['pass'])) {
        if (isset($users[$_POST['user']]) && isset($_POST['pass']) && password_verify($_POST['pass'], $users[$_POST['user']])) {
            $_SESSION['FileServerMngUser']['logged'] = $_POST['user'];
        } else {
            unset($_SESSION['FileServerMngUser']['logged']);
            $_SESSION['FileServerMngMessage'] = "<h2 style='color:red;'>Error Credentials :(</h2>";
        }
        header('location: ' . BASE_URL);
    }
    else {
        unset($_SESSION['FileServerMngUser']['logged']);
        $message = $_SESSION['FileServerMngMessage'] ?? "";
        $_SESSION['FileServerMngMessage'] = "";
        echo '
        <html>
            <head>
                <title>Yehuda\'s Files Server 😉 | Login</title>
                <script>console.log("%c‎E‎v‎e‎r‎y‎t‎h‎i‎n‎g‎ ‎i‎s‎ ‎p‎r‎otected‎,‎ ‎y‎o‎u‎ ‎w‎i‎l‎l‎ f‎i‎n‎d‎ ‎n‎o‎t‎h‎i‎n‎g‎ ‎h‎e‎r‎e‎ ‎😁‎", "color:red;font-size:30px;font-weight:bold;")</script>
            </head>
            <body>
                <div align="center">
                    <h1>Login - Files Server</h1>
                    ' . $message . '
                    <form method="POST">
                        <input type="text" name="user" placeholder="Username"><br><br>
                        <input type="password" name="pass" placeholder="Password"><br><br>
                        <button type="submit">Login</button>
                    </form>
                </div>
            </body>
        </html>';
    }
    die();
}

$act = $_GET['act'] ?? null;
$secretMode = $isLogged && ($_SESSION['secretMode'] ? true : false);

if(isset($act) && $isLogged){
    if($act == "folder"){
        $file = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $file;

        if(isset($_POST['name']) && $_POST['name']){
            if(is_dir($file)){
                $newName = $file . DS . basename($_POST['name']);

                if(file_exists($newName) || is_dir($newName)){
                    echo '<h1 align="center">Location Already Exists! ('.htmlspecialchars($newName).')<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                }
                else{
                    mkdir($newName);
                    if(is_dir($newName))
                        echo '<h1 align="center">Create Success!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                    else 
                        echo '<h1 align="center">Create Failed!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                }
            }
        }
        else{
            if(is_dir($file)){
                $name = $file;
            }
            else{
                header('location: ' . BASE_URL);
            }
            printHeader(getFakePath($file, $secretMode), $secretMode);
            echo '<form method="post" enctype="multipart/form-data"">
                    path: <b>'.htmlspecialchars(getFakePath($name, $secretMode)).'</b><br>
                    name: <input type="text" name="name"><br><br>
                    <input type="submit" value="Create Folder" name="submit">
                </form>';
        }
    }
    elseif($act == "upload"){
        $file = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $file;
        
        if(isset($_FILES["fileToUpload"]) && count($_FILES["fileToUpload"]["size"]) > 0){
            if(is_dir($file)){
                if(isset($_POST['secret']))
                    $targetDir = __DIR__ . DS . "p-f" . DS;
                else{
                    if(is_dir(($secretMode ? BASE_PATH_SF : BASE_PATH) . cleanPath($_POST['dir']))){
                        $targetDir = ($secretMode ? BASE_PATH_SF : BASE_PATH) . cleanPath($_POST['dir']);
                    }
                    else
                        $targetDir = $file;
                }

                $targetDir = realpath($targetDir) . DS;
                $override = $_POST['override'] ?? false;

                for($i = 0; $i < count($_FILES["fileToUpload"]["size"]); $i++){
                    $targetFile = $targetDir . basename($_FILES["fileToUpload"]["name"][$i]);
                    if((!$override && file_exists($targetFile)) || ($override && is_dir($targetFile)))
                        echo '<h1 align="center">File Already Exists! ('.htmlspecialchars(getFakePath($targetFile, $secretMode)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                    else{
                        move_uploaded_file($_FILES['fileToUpload']["tmp_name"][$i], $targetFile);
                    
                        if(file_exists($targetFile))
                            echo '<h1 align="center">Upload Success! ('.htmlspecialchars(getFakePath($targetFile, $secretMode)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                        else 
                            echo '<h1 align="center">Upload Failed! ('.htmlspecialchars(getFakePath($targetFile, $secretMode)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                    }
                }
            }
        }
        else{
            if(is_dir($file)){
                $name = $file;
            }
            else{
                header('location: ' . BASE_URL);
            }
            printHeader(getFakePath($file, $secretMode), $secretMode);
            echo '<form method="post" enctype="multipart/form-data">
                    <script>function dis(event){dir=document.getElementById("dir");dir.disabled=event.checked;}</script>
                    <input type="file" name="fileToUpload[]" id="fileToUpload" multiple="multiple"><br><br>
                    <input type="checkbox" name="secret" id="secret" onclick="dis(this)"><label for="secret">secret dir</label><br><br>
                    <input type="checkbox" name="override" id="override"><label for="override">Override</label><br><br>
                    dir: <input type="text" name="dir" id="dir" value="'.htmlspecialchars(getFakePath($name, $secretMode)).'"><br><br>
                    <input type="submit" value="Upload" name="submit">
                </form>';
        }
    }
    elseif($act == "secret"){
        $_SESSION['secretMode'] = true;
        header('location: ' . BASE_URL);
    }
    elseif($act == "public"){
        $_SESSION['secretMode'] = false;
        header('location: ' . BASE_URL);
    }
    elseif($act == "rename"){
        $oldName = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $file;
        
        if(isset($_POST['newName']) && $_POST['newName']){
            if(file_exists($oldName) || is_dir($oldName)){
                if((!in_array(basename($oldName), DONT_SHOW) || basename($oldName) == 'readme.md') && $oldName != ($secretMode ? BASE_PATH_SF : BASE_PATH)){
                    $newName = dirname($oldName) . DS . basename($_POST['newName']);

                    if(file_exists($newName) || is_dir($newName)){
                        echo '<h1 align="center">Location Already Exists! ('.htmlspecialchars($newName).')<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                    }
                    else{
                        rename($oldName, $newName);
                        if(file_exists($newName) || is_dir($newName))
                            echo '<h1 align="center">Rename Success!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                        else 
                            echo '<h1 align="center">Rename Failed!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                    }
                }
                else
                    echo '<h1 align="center">Error!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
            }
        }
        else{
            if(file_exists($oldName) || is_dir($oldName)){
                $name = $oldName;
            }
            else{
                header('location: ' . BASE_URL);
            }
            printHeader((is_dir($file) ? getFakePath($file, $secretMode) : getFakePath(dirname($file))), $secretMode);
            echo '<form method="post" enctype="multipart/form-data"">
                    path: <b>'.htmlspecialchars(getFakePath($name, $secretMode)).'</b><br>
                    new name: <input type="text" name="newName" value="'.htmlspecialchars(basename($name)).'"><br><br>
                    <input type="submit" value="ChangeName" name="submit">
                </form>';
        }
    }
    elseif($act == "delete"){
        $file = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $file;
        
        if(isset($_POST['delete']) && $_POST['delete']){
            if(file_exists($file) || is_dir($file)){
                if((!in_array(basename($file), DONT_SHOW) || basename($file) == 'readme.md') && $file != ($secretMode ? BASE_PATH_SF : BASE_PATH)){
                    del($file);
                    if(!file_exists($file))
                        echo '<h1 align="center">Delete Success!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                    else 
                        echo '<h1 align="center">Delete Failed!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
                }
                else
                    echo '<h1 align="center">Error!<br> [<a href="' . BASE_URL . '">go back</a>]</h1>';
            }
        }
        else{
            if(file_exists($file) || is_dir($file)){
                $name = $file;
            }
            else{
                header('location: ' . BASE_URL);
            }
            printHeader((is_dir($file) ? getFakePath($file, $secretMode) : getFakePath(dirname($file), $secretMode)), $secretMode);
            echo '<form method="post" enctype="multipart/form-data" onsubmit="return confirm(\'Yes I\\\'m totally sure\');">
                    <input type="checkbox" name="delete" id="delete"><label for="delete">Yes I\'m sure I want to delete <b>'.htmlspecialchars(getFakePath($name, $secretMode)).'</b></label><br><br>
                    <input type="submit" value="Delete" name="submit">
                </form>';
        }
    }
    else{
?>
<html>
    <head>
        <title>Yehuda's Files Server 😉</title>
        <script>console.log("%c‎E‎v‎e‎r‎y‎t‎h‎i‎n‎g‎ ‎i‎s‎ ‎p‎r‎otected‎,‎ ‎y‎o‎u‎ ‎w‎i‎l‎l‎ f‎i‎n‎d‎ ‎n‎o‎t‎h‎i‎n‎g‎ ‎h‎e‎r‎e‎ ‎😁‎", "color:red;font-size:30px;font-weight:bold;")</script>
    </head>
    <body>
        <h1>Not Found</h1>
        <h3><a href="<?php echo BASE_URL;?>">Go Back</a></h3>
    </body>
</html>
<?php
    }
    die();
}

if(is_dir(($secretMode ? BASE_PATH_SF : BASE_PATH) . $file)){
    printFilesTable($file, $isLogged, $secretMode);
}
else if(file_exists(($secretMode ? BASE_PATH_SF : BASE_PATH) . $file) && (!in_array(basename($file), DONT_SHOW) || ($isLogged && basename($file) == 'readme.md'))){
    $file = ($secretMode ? BASE_PATH_SF : BASE_PATH) . $file;
    header("Content-type: application/x-download");
    header("Content-Length: ". filesize($file));
    header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
    header("Content-Transfer-Encoding: binary");
    readfile($file); 
}
else{
?>
<html>
    <head>
        <title>Yehuda's Files Server 😉</title>
        <script>console.log("%c‎E‎v‎e‎r‎y‎t‎h‎i‎n‎g‎ ‎i‎s‎ ‎p‎r‎otected‎,‎ ‎y‎o‎u‎ ‎w‎i‎l‎l‎ f‎i‎n‎d‎ ‎n‎o‎t‎h‎i‎n‎g‎ ‎h‎e‎r‎e‎ ‎😁‎", "color:red;font-size:30px;font-weight:bold;")</script>
    </head>
    <body>
        <h1>Not Found</h1>
        <h3><a href="<?php echo BASE_URL;?>">Go Back</a></h3>
    </body>
</html>
<?php
}

