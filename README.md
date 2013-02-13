## Send example #1 ##

routing.yml:

```
pdf_download:
  url: /downloads/my-file
  param: { module: downloads, action: myFile }
  ...
```

actions.class.php:

```php
public function executeMyFile(sfWebRequest $request)
{
  mtContent::setFilename('Fájlnév 1.pdf'); // use any character in filename

  // ... code to create pdf content ...

  mtContent::send($pdf);
}
```

## Send example #2 ##

routing.yml:

```
json_service:
  url: /services/my-service.json # note the extension
  param: { module: services, action: myService }
  ...
```

actions.class.php:

```php
public function executeMyService(sfWebRequest $request)
{
  $json_content = array(
    'success' => true
  );

  mtContent::send($json_content); // arrays are automatically converted to json data
}
```

## Send example #3 ##

routing.yml:

```
json_service:
  url: /services/my-service # missing extension
  param: { module: services, action: myService }
  ...
```

actions.class.php:

```php
public function executeMyService(sfWebRequest $request)
{
  $json_content = true;

  mtContent::send(json_encode($json_content), array(
    'extension' => 'json'
  ));
}
```

## Upload example ##

```php
foreach ($request->getFiles() as $file)
{
  try
  {
    $filename = mtContent::moveUploadedFile($file, sfConfig::get('sf_upload_dir') . '/my_uploads');

    // store $filename in db
  }
  catch (Exception $e)
  {
  }
}
