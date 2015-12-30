Yii 2.0 Queue Extension
=========================

This extension provides queue handler for the [Yii framework 2.0](http://www.yiiframework.com).

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require yiisoft/yii2-queue
```

or add

```json
"yiisoft/yii2-queue": "dev-master"
```

to the require section of your composer.json.


Usage
-----

To use this extension,  simply add the following code in your application configuration:

####SqlQueue

```php
'components' => [
    'queue' => [
        'class' => 'yii\queue\SqlQueue',
    ],
],
```

####RedisQueue

```php
'components' => [
    'queue' => [
        'class' => 'yii\queue\RedisQueue',
        'redis' => '(your redis client)'
    ],
],
```

####SqsQueue

```php
'components' => [
    'queue' => [
        'class' => 'yii\queue\SqsQueue',
        'sqs' => '(your sqs client)'
    ],
],
```
