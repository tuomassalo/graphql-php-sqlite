# graphql-php-sqlite

A naïve GraphQL interface for querying photos.db from Apple Photos app.

## TODO:

- get image data:

  - [x] imagePath

  - [x] name (caption)

  - [x] lat/lng

  - [x] dimensions

  - [x] imageDate

  - [x] type

  - [ ] albums where the photo appears in?

- search criteria (all optional):

  - [x] q (freetext) (multi?)

  - [x] {lat,long}{Min,Max} = 12.34567

  - [ ] date{Min,Max}

  - [ ] type = image|video

  - [ ] album name

  - [ ] keywords automatically assigned by Photos

- [ ] fetch aggregate data (result count by date; geolocation clusters?)

- [ ] pagination

=======

1. Clone the repo
2. Copy `photos.db` to the same directory where you created the repo subdirectory. NB: You probably want to use a **copy** of `photos.db`, since the master database is usually locked in macOS.
3. Make sure you have composer installed, with e.g. `brew install composer`. Navigate to the project folder and run `composer install`
4. `docker run --rm -p 8080:80 -e LOG_STDOUT=true -e LOG_STDERR=true -e LOG_LEVEL=debug -v /Users/naapuri/dev/gallery2:/var/www/html fauria/lamp`
5. Install and open ChromeiQL extension to Chrome. Endpoint: http://localhost:8080/graphql-php-sqlite/server/api.php

Try with this query:

```
{
  photos {
    imagePath
    name
  }
}
```

Or this:

```
{
  photos(q:"foo") {
    imagePath
    name
  }
}
```
