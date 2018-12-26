<?php
require_once __DIR__ . '../../vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use GraphQL\Server\StandardServer;

try {
  $photoType = new ObjectType([
    'name' => 'photo',
    'fields' => [
      'imagePath' => ['type' => Type::string()],
      'name' => ['type' => Type::string()],
      'type' => ['type' => Type::string()],
      'latitude' => ['type' => Type::float()],
      'longitude' => ['type' => Type::float()],
      'width' => ['type' => Type::int()],
      'height' => ['type' => Type::int()],
      'imageDate' => ['type' => Type::int()], // unix timestamp, seconds since 1970-01-01
      'duration' => ['type' => Type::int()], // in seconds (rounded to integer)

    ]
  ]);

  $queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
      'photos' => [
        'type' => Type::listOf($photoType),
        'args' => [
          'q' => ['type' => Type::string(), 'defaultValue' => null],
          'latMin' => ['type' => Type::float(), 'defaultValue' => null],
          'latMax' => ['type' => Type::float(), 'defaultValue' => null],
          'lngMin' => ['type' => Type::float(), 'defaultValue' => null],
          'lngMax' => ['type' => Type::float(), 'defaultValue' => null],
        ],
        'resolve' => function ($root, $args) {
          try {
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

            error_log(json_encode([$conds, $vals]));

            $db = new SQLite3('../../photos.db');

            // see https://stackoverflow.com/questions/10746562/parsing-date-field-of-iphone-sms-file-from-backup/31454572#31454572
            $q = '
              SELECT
                RKMaster.imagePath,
                RKMaster.duration,
                RKVersion.processedWidth as width,
                RKVersion.processedHeight as height,
                RKVersion.name,
                RKVersion.latitude,
                RKVersion.longitude,
                RKVersion.adjustmentUUID,
                RKVersion.imageDate + 978307200 AS imageDate
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

              if($row['duration']) {
                $row['type'] = 'video';
                $row['duration'] = round($row['duration']);
              } else {
                $row['type'] = 'image';
              }
              $resultArr[] = $row;
            }

            // error_log(json_encode([resultArr => $resultArr]));

            return $resultArr;
          } catch (\Exception $e) {
            error_log("INTERNAL ERROR:");
            error_log($e);
            StandardServer::send500Error('INTERNAL ERROR');
          }
        }
      ],
    ],
  ]);

  // See docs on schema options:
  // http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
  $schema = new Schema([
    'query' => $queryType,
    // 'mutation' => $mutationType,
  ]);

  // See docs on server options:
  // http://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
  $server = new StandardServer([
      'schema' => $schema
  ]);

  $server->handleRequest();

} catch (\Exception $e) {
    StandardServer::send500Error($e);
}
