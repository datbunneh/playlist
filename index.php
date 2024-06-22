<?php

require_once __DIR__ . '/library.php';

define('DEFAULT_GROUP', '__DEFAULT__');

session_start();

$directoryItems = array_slice(scandir(FILES_DIRECTORY), 2);
$directories = [];

foreach ($directoryItems as $item) {
    if (!is_dir(getDirectoryPath($item))) {
        continue;
    }

    $directories[] = $item;
}

if ($_POST) {
    $_SESSION['directory'] = $_POST['directory'];

    if (!$_SESSION['directory']) {
        halt('Select directory from the dropdown.');
    }
}

if (isset($_POST['generate'])) {
    $customGroups = getCustomGroups($_SESSION['directory']);
    $filesList = getDirectoryFilesList($_SESSION['directory']);

    if (!$filesList) {
        halt('Directory does not exist or contains no .mp3 files in it.');
    }

    $filesData = getFilesData($filesList, $customGroups);
    $songsQuantity = count($filesData);
    $groupsData = getGroupsData($filesData);
    $groupsBySize = getGroupsBySize($groupsData);
    $batchSize = array_key_first($groupsBySize);
    $batchCombinations = getBatchCombinations($groupsBySize, $batchSize, $songsQuantity);
    $batchLists = getBatchLists($batchCombinations, $groupsData, $batchSize);
    $playlistMap = getPlaylistMap($batchLists, $filesData, $batchSize, $songsQuantity);

    $_SESSION['playlistMap'] = $playlistMap;
    $_SESSION['playlistMapDirectory'] = $_SESSION['directory'];

    halt('Playlist map is successfully generated.');
}

if (isset($_POST['stripIndexes'])) {
    $customGroups = getCustomGroups($_SESSION['directory']);
    $filesList = getDirectoryFilesList($_SESSION['directory']);

    if (!$filesList) {
        halt('Directory does not exist or contains no .mp3 files in it.');
    }

    $filesData = getFilesData($filesList, $customGroups);
    $directoryPath = getDirectoryPath($_SESSION['directory']);

    foreach ($filesData as $fileName => $fileData) {
        $newFileName = $fileData['show'] !== DEFAULT_GROUP ? "{$fileData['show']} - {$fileData['song']}.mp3" : "{$fileData['song']}.mp3";

        if ($fileName === $newFileName) {
            continue;
        }

        rename("$directoryPath/$fileName", "$directoryPath/$newFileName");
    }

    halt('File indexes are successfully stripped.');
}

if (isset($_POST['empty'])) {
    $filesList = getDirectoryFilesList($_SESSION['directory']);

    if (!$filesList) {
        halt('Directory does not exist or contains no .mp3 files in it.');
    }

    $directoryPath = getDirectoryPath($_SESSION['directory']);

    foreach ($filesList as $fileName) {
        unlink("$directoryPath/$fileName");
    }

    halt('Directory is successfully emptied.');
}

if (isset($_POST['apply'])) {
    if (!isset($_SESSION['playlistMap']) || !isset($_SESSION['playlistMapDirectory'])) {
        halt('Playlist map is not generated or corrupted.');
    }

    $filesNames = array_keys($_SESSION['playlistMap']);
    $directoryPath = getDirectoryPath($_SESSION['playlistMapDirectory']);

    foreach ($filesNames as $fileName) {
        if (is_file("$directoryPath/$fileName")) {
            continue;
        }

        halt('Playlist map is outdated and needs to be regenerated.');
    }

    foreach ($_SESSION['playlistMap'] as $fileName => $newFileName) {
        if ($fileName === $newFileName) {
            continue;
        }

        rename("$directoryPath/$fileName", "$directoryPath/$newFileName");
    }

    halt('Files are successfully rearranged according to the generated playlist.');
}

if (isset($_POST['delete'])) {
    unset($_SESSION['playlistMap']);
    unset($_SESSION['playlistMapDirectory']);

    halt('Last generated playlist is successfully deleted.');
}

?>

<html>
    <head>
        <title>Playlist Generator</title>
    </head>

    <body>
        <form method="post">
            <?php if (isset($_SESSION['message'])): ?>
                <div><?= $_SESSION['message'] ?></div>
                <br/>
                <?php unset($_SESSION['message']) ?>
            <?php endif ?>

            <select name="directory">
                <option value="">---Select directory---</option>

                <?php foreach ($directories as $directory): ?>
                    <option value="<?= $directory ?>"<?= isset($_SESSION['directory']) && $_SESSION['directory'] === $directory ? ' selected' : '' ?>><?= $directory ?></option>
                <?php endforeach ?>
            </select>

            <input type="submit" name="generate" value="Generate"/>
            <input type="submit" name="stripIndexes" value="Strip Indexes"/>
            <input type="submit" name="empty" value="Empty" onclick="return confirm('Are you sure you want to empty the directory?')"/>

            <?php if (isset($_SESSION['playlistMap']) && isset($_SESSION['playlistMapDirectory'])): ?>
                <div style="font-family: Consolas; font-size: 13px">
                    <h3>Last Generated Playlist</h3>
                    <span><b>Directory:</b> <?= $_SESSION['playlistMapDirectory'] ?></span>
                    <br/>
                    <input type="submit" name="apply" value="Apply"/>
                    <input type="submit" name="delete" value="Delete" onclick="return confirm('Are you sure you want to delete last generated playlist?')"/>
                    <br/>

                    <?php foreach ($_SESSION['playlistMap'] as $fileName): ?>
                        <br/>
                        <?= $fileName ?>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </form>
    </body>
</html>
