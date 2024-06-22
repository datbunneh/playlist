<?php

define('CONFIG_FILE_NAME', 'config.json');
define('FILES_DIRECTORY', 'files');

function getDirectoryPath($directory)
{
    return sprintf('%s/%s', FILES_DIRECTORY, $directory);
}

function getCustomGroups($directory)
{
    $configFilePath = sprintf('%s.json', getDirectoryPath($directory));
    $customGroups = [];

    if (is_file($configFilePath)) {
        $config = json_decode(file_get_contents($configFilePath), true) ?: [];
        $customGroups = $config['customGroups'] ?? [];
    }

    return $customGroups;
}

function getDirectoryFilesList($directory)
{
    $directoryPath = getDirectoryPath($directory);

    if (!is_dir($directoryPath)) {
        return null;
    }

    $files = array_slice(scandir($directoryPath), 2);
    $verifiedFiles = [];

    foreach ($files as $fileName) {
        if (!is_file("$directoryPath/$fileName")) {
            continue;
        }

        if (!preg_match('~\\.mp3$~i', $fileName)) {
            continue;
        }

        $verifiedFiles[] = $fileName;
    }

    return $verifiedFiles;
}

function getFilesData($files, $customGroups = [])
{
    $filesData = [];

    foreach ($files as $fileName) {
        if (!preg_match('~^(?:\\d+\\. )?(?:(.+) - )?(.+)\\.mp3$~i', $fileName, $matches)) {
            continue;
        }

        $groupName = $showName = $matches[1];
        $songName = $matches[2];

        foreach ($customGroups as $customGroupName => $showNames) {
            if (!in_array($showName, $showNames)) {
                continue;
            }

            $groupName = $customGroupName;

            break;
        }

        $filesData[$fileName] = [
            'group' => $groupName ?: DEFAULT_GROUP,
            'show' => $showName ?: DEFAULT_GROUP,
            'song' => $songName
        ];
    }

    if (!$filesData) {
        throw new Exception('No valid mp3 files found.');
    }

    return $filesData;
}

function getGroupsData($filesData)
{
    $groupsData = [];

    foreach ($filesData as $fileName => $fileData) {
        $groupsData[$fileData['group']][] = $fileName;
    }

    foreach ($groupsData as $groupName => $fileNames) {
        shuffle($fileNames);
        $groupsData[$groupName] = $fileNames;
    }

    return $groupsData;
}

function getGroupsBySize($groupsData)
{
    $groupsBySize = [];

    foreach ($groupsData as $groupName => $fileNames) {
        $groupsBySize[count($fileNames)][] = $groupName;
    }

    foreach ($groupsBySize as $groupSize => $groupNames) {
        shuffle($groupNames);
        $groupsBySize[$groupSize] = $groupNames;
    }

    krsort($groupsBySize);

    return $groupsBySize;
}

function getBatchCombinations($groupsBySize, $batchSize, $songsQuantity)
{
    $minBatchesQuantity = ceil($songsQuantity / $batchSize);
    $batchCombinations = [];
    $batchCombinationSizes = [];

    while ($groupsBySize) {
        $currentGroupSize = array_key_first($groupsBySize);
        $groupName = array_pop($groupsBySize[$currentGroupSize]);

        if (!$groupsBySize[$currentGroupSize]) {
            unset($groupsBySize[$currentGroupSize]);
        }

        if (count($batchCombinations) < $minBatchesQuantity) {
            $batchCombinations[] = [$groupName];
            $batchCombinationSizes[] = $currentGroupSize;

            continue;
        }

        asort($batchCombinations);
        asort($batchCombinationSizes);

        foreach ($batchCombinations as $index => $groups) {
            if ($batchCombinationSizes[$index] + $currentGroupSize > $batchSize) {
                continue;
            }

            $batchCombinations[$index][] = $groupName;
            $batchCombinationSizes[$index] += $currentGroupSize;

            continue 2;
        }

        $batchCombinations[] = [$groupName];
        $batchCombinationSizes[] = $currentGroupSize;
    }

    shuffle($batchCombinations);

    return $batchCombinations;
}

function getBatchCombinationSize($batchCombination, $groupsData)
{
    $batchSize = 0;

    foreach ($batchCombination as $groupName) {
        $batchSize += count($groupsData[$groupName]);
    }

    return $batchSize;
}

function getBatchLists($batchCombinations, $groupsData, $batchSize)
{
    $batchLists = [];

    foreach ($batchCombinations as $batchCombination) {
        $batchLists[] = getBatchList($batchCombination, $groupsData, $batchSize);
    }

    return $batchLists;
}

function getBatchList($batchCombination, $groupsData, $batchSize)
{
    $batchList = array_fill(0, $batchSize, null);
    $slotsQuantity = $batchSize;

    foreach ($batchCombination as $groupName) {
        $groupFiles = $groupsData[$groupName];
        $slotsLeftQuantity = $slotsQuantity;

        foreach ($batchList as $index => $slotValue) {
            if ($slotValue) {
                continue;
            }

            if (count($groupFiles) / $slotsLeftQuantity * 100 < rand(1, 100)) {
                $slotsLeftQuantity -= 1;

                continue;
            }

            $batchList[$index] = array_pop($groupFiles);

            $slotsLeftQuantity -= 1;
            $slotsQuantity -= 1;

            if (!$groupFiles) {
                break;
            }
        }
    }

    return $batchList;
}

function getPlaylistMap($batchList, $filesData, $batchSize, $songsQuantity)
{
    $indexSize = strlen($songsQuantity);
    $playlistMap = [];
    $songIndex = 1;

    for ($index = 0; $index < $batchSize; $index += 1) {
        foreach ($batchList as $batch) {
            $fileName = $batch[$index];

            if (!$fileName) {
                continue;
            }

            $fileData = $filesData[$fileName];
            $fileNameNew = $fileData['show'] !== DEFAULT_GROUP
                ? sprintf('%s. %s - %s.mp3', str_pad($songIndex, $indexSize, '0', STR_PAD_LEFT), $fileData['show'], $fileData['song'])
                : sprintf('%s. %s.mp3', str_pad($songIndex, $indexSize, '0', STR_PAD_LEFT), $fileData['song']);
            $playlistMap[$fileName] = $fileNameNew;
            $songIndex += 1;
        }
    }

    return $playlistMap;
}

function halt($message = null)
{
    if ($message) {
        $_SESSION['message'] = $message;
    }

    header('Location: index.php');
    exit;
}
