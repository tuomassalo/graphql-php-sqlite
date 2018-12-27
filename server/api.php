<?php
require_once __DIR__ . '../../vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use GraphQL\Server\StandardServer;

require __DIR__ . '/query.php';

# TODO: move to env
const LIBRARY_PATH = '/var/www/html/photosroot';

try {

  $mediaTypeEnum = new EnumType([
    'name' => 'mediaType',
    'description' => 'is it a still or a video clip?',
    'values' => ['IMAGE', 'VIDEO']
  ]);

  $photoType = new ObjectType([
    'name' => 'photo',
    'fields' => [
      'path' => ['type' => Type::string()],
      'caption' => ['type' => Type::string()],
      'type' => ['type' => $mediaTypeEnum],
      'lat' => ['type' => Type::float()],
      'lng' => ['type' => Type::float()],
      'width' => ['type' => Type::int()],
      'height' => ['type' => Type::int()],
      'date' => ['type' => Type::float()], // unix timestamp, seconds since 1970-01-01
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
          'dateMin' => ['type' => Type::float(), 'defaultValue' => null],
          'dateMax' => ['type' => Type::float(), 'defaultValue' => null],
          'type' => ['type' => $mediaTypeEnum, 'defaultValue' => null],
        ],
        'resolve' => function ($root, $args) {
          try {
            return findPhotos($args);
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
