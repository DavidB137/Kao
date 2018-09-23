# Kao

Simple to use and lightweight PHP caching class.

#### Requirements

PHP 5.6 or newer

## Install

1. The whole class is one file, so just download it and add line this to the beginning of your PHP file:

   ```php
   require "PATH/TO/kao.class.php";
   ```

2. Create subfolder `cache` in folder where you downloaded `kao.class.php`.
3. Change [configuration](#configuration) if necessary.

## Configuration

You can adjust the configuration if you need to. It's directly implemenented at the beginning of `kao.class.php` (around line 16). Currently there are these settings:
- **dirCache**: directory where cache will be stored *(have to exist before use!)*
  - default: `__DIR__ . "/cache"`
- **id_hashAlgo**: hashing algorithm used to hash identifier
  - default: `"md5"`
  - values: any hashing algorithm supported by your version of PHP, e.g.: md5, sha256,...
- **returnPathType**: type of path (relative or absolute) used in return values of functions
  - default: `"absolute"`
  - values: relative, absolute
- **dirCreatePermissions**: directory permissions to use when creating new folders (in octal)
  - default: `0750`


## Usage

The way this class works is: **create** cache and save it -> **read** saved cache when is needed -> **remove** old cache.

Best practice of this is reading cache where you need it, but creating (and also removing old one) using another file called by CRON job (in certain interval).

### Supported data types

#### `www`

Stores and reads/returns website source as *string*.

**Example:**
- source of `http://example.com`:
  ```html
  <!doctype html>
  <html>
     ...
  </html>
  ```
- cron.php:
  ```php
  $kao = new Kao("example-html", "www");
  $kao->create("http://example.com");
  $kao->remove_old(600);
  ```
- index.php:
  ```php
  $kao = new Kao("example-html", "www");
  $content = $kao->read();
  echo($content);

  // RESULT:
  // <!doctype html>
  // <html>
  //    ...
  // </html>
  ```

---

#### `www_json`

For websites with JSON content. JSON is downloaded from website, stored, and converted to PHP *array* when you want to `read()` it.

**Example:**
- source of `https://jsonplaceholder.typicode.com/comments/1/`:
  ```json
  {
     "postId": 1,
     "id": 1,
     "name": "id labore ex et quam laborum",
     "email": "Eliseo@gardner.biz",
     "body": "laudantium ... accusantium"
  }
  ```
- cron.php:
  ```php
  $kao = new Kao("latestComment", "www_json");
  $kao->create("https://jsonplaceholder.typicode.com/comments/1/");
  $kao->remove_old(600);
  ```
- index.php:
  ```php
  $kao = new Kao("latestComment", "www_json");
  $content = $kao->read();
  print_r($content);

  // RESULT:
  // Array (
  //    [postId] => 1
  //    [id] => 1
  //    [name] => id labore ex et quam laborum
  //    [email] => Eliseo@gardner.biz
  //    [body] => laudantium ... accusantium
  // )
  ```

---

#### `string`

Stores and reads/returns direct *string* input.

**Example:**
- cron.php:
  ```php
  $kao = new Kao("example-text", "string");
  $kao->create("Lorem ipsum...");
  $kao->remove_old(600);
  ```
- index.php:
  ```php
  $kao = new Kao("example-text", "string");
  $content = $kao->read();
  echo($content);

  // RESULT:
  // Lorem ipsum...
  ```

---

#### `array`

Stores direct *array* input (as JSON and converts it back on `read()`).

**Example:**
- cron.php:
  ```php
  $kao = new Kao("example-array", "array");
  $kao->create(array("userid" => "147", "username" => "KaoExample123"));
  $kao->remove_old(600);
  ```
- index.php:
  ```php
  $kao = new Kao("example-array", "array");
  $content = $kao->read();
  print_r($content);

  // RESULT:
  // Array (
  //    [userid] => 147
  //    [username] => KaoExample123
  // )
  ```


## Functions

### Construct

**Parameters:**
- *string* `$id`: identifier
- *string* `$dataType`: one of supported [data types](#supported-data-types)

**Return:** *void*

**Syntax:**
```php
$kao = new Kao($id, $dataType);
```

---

### create()

Creates cache files

**Parameters:**
- *string|array* `$input`: www address or direct input

**Return:** *(string)* path of created cache file

**Syntax:**
```php
$kao->create($input);
```

---

### read()

Reads cache files

**Parameters:** no parameters

**Return:** *(string|array)* latest available cache (according to [dataType](#supported-data-types))

**Syntax:**
```php
$kao->read();
```

---

### remove_old()

Removes old cache

I strongly recommend to always have at least two versions of cache (new and one older), to eliminate situations like: you have a huge file cached and in the middle of loading it by one of your PHP scripts you delete that cache file and your website will display an error. So simple math: best `$age` is 2 - 3 times interval of creating new cache files, e.g. if you run `create()` function every 5 minutes, correct value is 600 seconds (10 minutes) or more.

**Parameters:**
- *int* `$age`: min. age of files to remove (in seconds)

**Return:** *(array)* list of removed files

**Syntax:**
```php
$kao->remove_old($age);
```

---

### delete()

Deletes everything by `id` - all cache files and file with data

**Parameters:** no parameters

**Return:** *(array)* list of deleted files

**Syntax:**
```php
$kao->delete();
```

## Credits

Kao was created by Dávid Benko. Released under [MIT](https://opensource.org/licenses/MIT) license.
