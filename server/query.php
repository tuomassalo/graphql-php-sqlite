<?php
require_once __DIR__ . '../../vendor/autoload.php';

# TODO: move to env
const LIBRARY_PATH = '/var/www/html/photosroot';
const DATE_OFFSET = 978307200.0;

function findPhotos($args) {

  error_log(json_encode([$root, $args]));
  $conds = [
    'RKVersion.nonRawMasterUuid = RKMaster.uuid',
    'RKVersion.isInTrash = 0',
    'RKMaster.isInTrash = 0',
  ];
  $vals = [];

  // freetext search
  if($args['q']) {
    // $conds[] = 'RKVersion.name LIKE :q';
    // $vals[] = [':q', '%' . $args['q'] . '%', SQLITE3_TEXT]; // NB: no escaping for _ or %
    // TMP!
    $conds[] = '(RKVersion.name LIKE :q OR RKVersion.uuid LIKE :q)';
    $vals[] = [':q', '%' . $args['q'] . '%', SQLITE3_TEXT]; // NB: no escaping for _ or %
    $vals[] = [':q', '%' . $args['q'] . '%', SQLITE3_TEXT]; // NB: no escaping for _ or %
  }

  // geolocation
  if($args['latMin']) {
    $conds[] = 'RKVersion.latitude >= :latMin';
    $vals[] = [':latMin', $args['latMin'], SQLITE3_FLOAT];
  }
  if($args['latMax']) {
    $conds[] = 'RKVersion.latitude <= :latMax';
    $vals[] = [':latMax', $args['latMax'], SQLITE3_FLOAT];
  }
  if($args['lngMin']) {
    $conds[] = 'RKVersion.longitude >= :lngMin';
    $vals[] = [':lngMin', $args['lngMin'], SQLITE3_FLOAT];
  }
  if($args['lngMax']) {
    $conds[] = 'RKVersion.longitude <= :lngMax';
    $vals[] = [':lngMax', $args['lngMax'], SQLITE3_FLOAT];
  }

  // image date
  if($args['dateMin']) {
    $conds[] = 'RKVersion.imageDate >= :dateMin';
    $vals[] = [':dateMin', $args['dateMin'] - DATE_OFFSET, SQLITE3_FLOAT];
  }
  if($args['dateMax']) {
    $conds[] = 'RKVersion.imageDate <= :dateMax';
    $vals[] = [':dateMax', $args['dateMax'] - DATE_OFFSET, SQLITE3_FLOAT];
  }

  if($args['type']) {
    if($args['type'] == 'IMAGE') {
      $conds[] = 'RKMaster.duration IS NULL';
    } else if($args['type'] == 'VIDEO') {
      $conds[] = 'RKMaster.duration IS NOT NULL';
    }

    $conds[] = 'RKVersion.imageDate <= :dateMax';
    $vals[] = [':dateMax', $args['dateMax'] - DATE_OFFSET, SQLITE3_FLOAT];
  }

  error_log(json_encode([$conds, $vals]));

  $db = new SQLite3('../../photos.db');

  // see https://stackoverflow.com/questions/10746562/parsing-date-field-of-iphone-sms-file-from-backup/31454572#31454572
  $q = '
    SELECT
      RKMaster.imagePath AS path,
      RKMaster.duration,
      RKVersion.processedWidth as width,
      RKVersion.processedHeight as height,
      RKVersion.name,
      RKVersion.latitude AS lat,
      RKVersion.longitude AS lng,
      RKVersion.modelId,
      RKVersion.adjustmentUuid,
      RKVersion.imageDate + ' . DATE_OFFSET . ' AS date
    FROM
      RKVersion,
      RKMaster
    WHERE
    '. implode(" AND ", $conds) . '
    LIMIT 50
  ';
  error_log($q);

  $stmt = $db->prepare($q);
  foreach($vals as $v) {
    $stmt->bindValue($v[0], $v[1], $v[2]);
  }
  $result = $stmt->execute();

  $resultArr = [];
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    error_log(json_encode( $row));

    // Adapted from https://github.com/tymmej/ExportPhotosLibrary/blob/2392cfa33e496ca188468f93e6734482ebfa0161/ExportPhotosLibrary.py#L207
    if($row['adjustmentUuid'] != "UNADJUSTEDNONRAW" and $row['adjustmentUuid'] != "UNADJUSTED") {
      // resourceType=4 is possibly the full size image
      $stmt2 = $db->prepare("
        SELECT modelId FROM RKModelResource
        WHERE
          UTI = 'public.jpeg'
          AND resourceTag = :auuid
          AND attachedModelId = :vid
        ORDER BY resourceType
      ");
      $stmt2->bindValue(':vid', $row['modelId']);
      $stmt2->bindValue(':auuid', $row['adjustmentUuid']);
      $result2 = $stmt2->execute();
      $row['path'] = getResourcePath($result2->fetchArray()['modelId']);
    } else {
      $row['path'] = 'Masters/' . $row['path'];
    }

    if($row['duration']) {
      $row['type'] = 'VIDEO';
      $row['duration'] = round($row['duration']);
    } else {
      $row['type'] = 'IMAGE';
    }
    $resultArr[] = $row;
  }

  // error_log(json_encode([resultArr => $resultArr]));

  return $resultArr;
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function getResourcePath($modelId) {

  $modelIdHex = dechex($modelId);
  $paddedModelIdHex = substr("0000".$modelIdHex,-4);

  $folderName = substr($paddedModelIdHex, 0, 2);

  $fileEndsWith = '_' . $modelIdHex . '.jpeg';

  $it = new RecursiveDirectoryIterator(LIBRARY_PATH . '/resources/media/version/' . $folderName);
  foreach(new RecursiveIteratorIterator($it) as $file) {
    // error_log($file);
    // error_log(json_encode($file));
    if(endsWith(''.$file, $fileEndsWith)) {
      return ''.$file;
    }

  }
  return null;
}
