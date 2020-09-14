<?php

define('DONT_SHOW', array('.', '..', ".htaccess"));
define('DS', DIRECTORY_SEPARATOR);
define('BASE_PATH', __DIR__ . DS . "data" . DS);

if(file_exists(substr(BASE_PATH, 0, -1)) && !is_dir(substr(BASE_PATH, 0, -1))) die("<h1 style='color:red'>Fatal Error!<h1>");
if(!is_dir(BASE_PATH)) mkdir(BASE_PATH);
if(!file_exists(BASE_PATH . '.htaccess')) file_put_contents(BASE_PATH . '.htaccess', 'deny from all');

$users = array(
    "admin" => "admin@123",
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
function getFakePath($path){
    return cleanPath(substr($path, strlen(BASE_PATH)));
}

$file = cleanPath($_GET['file'] ?? "");
if(empty($file)) $file = "";

if(isset($_GET['login']) || isset($_GET['logout'])){
    if(!isset($_GET['logout']) && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && isset($users[$_SERVER['PHP_AUTH_USER']]) && $users[$_SERVER['PHP_AUTH_USER']] == $_SERVER['PHP_AUTH_PW']){
        header("location: /");
    }
    else{
        header('WWW-Authenticate: Basic realm="Files Server Auth"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<span>You do not have permission to view this page!<br><button onclick="location.reload();">Click here to try again</button></span><br>[<a href="/">go back</a>]';
    }
    die();
}

if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && isset($users[$_SERVER['PHP_AUTH_USER']]) && $users[$_SERVER['PHP_AUTH_USER']] == $_SERVER['PHP_AUTH_PW'])
    $isLogged = true;


$act = $_GET['act'] ?? null;
if(isset($act) && $isLogged){
    if($act == "folder"){
        $file = BASE_PATH . $file;
        
        if(isset($_POST['name']) && $_POST['name']){
            if(is_dir($file)){
                $newName = $file . DS . basename($_POST['name']);

                if(file_exists($newName) || is_dir($newName)){
                    echo '<h1 align="center">Location Already Exists! ('.htmlspecialchars($newName).')<br> [<a href="/">go back</a>]</h1>';
                }
                else{
                    mkdir($newName);
                    if(is_dir($newName))
                        echo '<h1 align="center">Create Success!<br> [<a href="/">go back</a>]</h1>';
                    else 
                        echo '<h1 align="center">Create Failed!<br> [<a href="/">go back</a>]</h1>';
                }
            }
        }
        else{
            echo "<p>Hello <b>" . htmlspecialchars($_SERVER['PHP_AUTH_USER']) . "</b>. [<a href='?logout'>logout</a>] [<a href='/'>home</a>]</p>";
            
            if(is_dir($file)){
                $name = $file;
            }
            else{
                header('location: /');
            }
            echo '<form method="post" enctype="multipart/form-data"">
                    path: <b>'.htmlspecialchars($name).'</b><br>
                    name: <input type="text" name="name"><br><br>
                    <input type="submit" value="Create Folder" name="submit">
                </form>';
        }
    }
    elseif($act == "upload"){
        $file = BASE_PATH . $file;
        
        if(isset($_FILES["fileToUpload"]) && count($_FILES["fileToUpload"]["size"]) > 0){
            if(is_dir($file)){
                if(isset($_POST['password']))
                    $targetDir = __DIR__ . DS . "p-f" . DS;
                else{
                    if(is_dir(BASE_PATH . cleanPath($_POST['dir']))){
                        $targetDir = BASE_PATH . cleanPath($_POST['dir']);
                    }
                    else
                        $targetDir = $file;
                }

                $targetDir = realpath($targetDir) . DS;

                for($i = 0; $i < count($_FILES["fileToUpload"]["size"]); $i++){
                    $targetFile = $targetDir . basename($_FILES["fileToUpload"]["name"][$i]);
                    if(file_exists($targetFile))
                        echo '<h1 align="center">File Already Exists! ('.htmlspecialchars(getFakePath($targetFile)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                    else{
                        move_uploaded_file($_FILES['fileToUpload']["tmp_name"][$i], $targetFile);
                    
                        if(file_exists($targetFile))
                            echo '<h1 align="center">Upload Success! ('.htmlspecialchars(getFakePath($targetFile)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                        else 
                            echo '<h1 align="center">Upload Failed! ('.htmlspecialchars(getFakePath($targetFile)).')<br> [<a href="?act=upload">go back</a>]</h1>';
                    }
                }
            }
        }
        else{
            echo "<p>Hello <b>" . htmlspecialchars($_SERVER['PHP_AUTH_USER']) . "</b>. [<a href='?logout'>logout</a>] [<a href='/'>home</a>]</p>";
            
            if(is_dir($file)){
                $name = $file;
            }
            else{
                header('location: /');
            }
            echo '<form method="post" enctype="multipart/form-data">
                    <script>function dis(event){dir=document.getElementById("dir");dir.disabled=event.checked;}</script>
                    <input type="file" name="fileToUpload[]" id="fileToUpload" multiple="multiple"><br><br>
                    <input type="checkbox" name="password" id="password" onclick="dis(this)"><label for="password">password dir</label><br><br>
                    dir: <input type="text" name="dir" id="dir" value="'.htmlspecialchars(getFakePath($name)).'"><br><br>
                    <input type="submit" value="Upload" name="submit">
                </form>';
        }
    }
    elseif($act == "password"){
        die("In Building...");
    }
    elseif($act == "rename"){
        $oldName = BASE_PATH . $file;
        
        if(isset($_POST['newName']) && $_POST['newName']){
            if(file_exists($oldName) || is_dir($oldName)){
                if(!in_array(basename($oldName), DONT_SHOW) && $oldName != BASE_PATH){
                    $newName = dirname($oldName) . DS . basename($_POST['newName']);

                    if(file_exists($newName) || is_dir($newName)){
                        echo '<h1 align="center">Location Already Exists! ('.htmlspecialchars($newName).')<br> [<a href="/">go back</a>]</h1>';
                    }
                    else{
                        rename($oldName, $newName);
                        if(file_exists($newName) || is_dir($newName))
                            echo '<h1 align="center">Rename Success!<br> [<a href="/">go back</a>]</h1>';
                        else 
                            echo '<h1 align="center">Rename Failed!<br> [<a href="/">go back</a>]</h1>';
                    }
                }
                else
                    echo '<h1 align="center">Error!<br> [<a href="/">go back</a>]</h1>';
            }
        }
        else{
            echo "<p>Hello <b>" . htmlspecialchars($_SERVER['PHP_AUTH_USER']) . "</b>. [<a href='?logout'>logout</a>] [<a href='/'>home</a>]</p>";
            
            if(file_exists($oldName) || is_dir($oldName)){
                $name = $oldName;
            }
            else{
                header('location: /');
            }
            echo '<form method="post" enctype="multipart/form-data"">
                    path: <b>'.htmlspecialchars($name).'</b><br>
                    new name: <input type="text" name="newName" value="'.htmlspecialchars(basename($name)).'"><br><br>
                    <input type="submit" value="ChangeName" name="submit">
                </form>';
        }
    }
    elseif($act == "delete"){
        $file = BASE_PATH . $file;
        
        if(isset($_POST['delete']) && $_POST['delete']){
            if(file_exists($file) || is_dir($file)){
                if(!in_array(basename($file), DONT_SHOW) && $file != BASE_PATH){
                    del($file);
                    if(!file_exists($file))
                        echo '<h1 align="center">Delete Success!<br> [<a href="/">go back</a>]</h1>';
                    else 
                        echo '<h1 align="center">Delete Failed!<br> [<a href="/">go back</a>]</h1>';
                }
                else
                    echo '<h1 align="center">Error!<br> [<a href="/">go back</a>]</h1>';
            }
        }
        else{
            echo "<p>Hello <b>" . htmlspecialchars($_SERVER['PHP_AUTH_USER']) . "</b>. [<a href='?logout'>logout</a>] [<a href='/'>home</a>]</p>";
            
            if(file_exists($file) || is_dir($file)){
                $name = $file;
            }
            else{
                header('location: /');
            }
            echo '<form method="post" enctype="multipart/form-data" onsubmit="return confirm(\'Yes I\\\'m totally sure\');">
                    <input type="checkbox" name="delete" id="delete"><label for="delete">Yes I\'m sure I want to delete <b>'.htmlspecialchars($name).'</b></label><br><br>
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
        <h3><a href="/">Go Back</a></h3>
    </body>
</html>
<?php
    }
    die();
}

if(is_dir(BASE_PATH . $file)){
?>
<html>
    <head>
        <title>Yehuda's Files Server 😉 | <?php echo empty($file) ? "home" : $file; ?></title>
        <script>console.log("%c‎E‎v‎e‎r‎y‎t‎h‎i‎n‎g‎ ‎i‎s‎ ‎p‎r‎otected‎,‎ ‎y‎o‎u‎ ‎w‎i‎l‎l‎ f‎i‎n‎d‎ ‎n‎o‎t‎h‎i‎n‎g‎ ‎h‎e‎r‎e‎ ‎😁‎", "color:red;font-size:30px;font-weight:bold;")</script>
    </head>
    <body>
    <?php if($isLogged) echo "<p>Hello <b>" . htmlspecialchars($_SERVER['PHP_AUTH_USER']) . "</b>. 
        [<a href='/?logout'>logout</a>] 
        [<a href='/".$file."?act=upload'>upload</a>] 
        [<a href='/?act=password'>password files</a>] 
        [<a href='/".$file."?act=folder'>create folder</a>] 
    </p>
    "; ?>
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

if(!empty($file)){
    ?>
        <tr>
            <td><a href='/'>Go Back</a></td>
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
            <td><a href='?login'>Login</a></td>
            <td>-</td>
            <td>-</td>
        </tr>
    <?php
}

foreach (scandir(BASE_PATH . $file) as $object){
    $object = BASE_PATH . $file . DS .  $object;
    $name = basename($object);
    
    if(in_array($name, DONT_SHOW)) continue;

    $htmlName = htmlspecialchars($name);
    $link = "/" . getFakePath($object);
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
    </body>
</html>

<?php }
else if(file_exists(BASE_PATH . $file)){
    $file = BASE_PATH . $file;
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
        <h3><a href="/">Go Back</a></h3>
    </body>
</html>
<?php
}


