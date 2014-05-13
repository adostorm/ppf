<?php

/**
 * Add your routes here
 */
$app->get('/', function () use ($app) {
    echo $app['view']->getRender(null, 'index');
});

/**
 * Not found handler
 */
$app->notFound(function () use ($app) {
    echo "<h1>404 NOT FOUND</h1>";
});

$app->before(function() use ($app) {
    \Util\TokenProof::check($app);
});

$feedController = new FeedController();
/**
 * test case :
 * curl -d "app_id=1&source_id=1&type=1&type_id=121212&author_id=1231&author=塑料袋&content=aaaaaaaaaa&create_time=12121231" http://feed.api.mama.com/feed/create
 */
$app->post('/feed/create', array($feedController, 'create'));

/**
 * curl -i -X GET 'http://feed.api.mama.com/statuses/public_timeline?app_id=1'
 */
$app->get('/statuses/public_timeline', array($feedController, 'getFeedListByAppId'));

/**
 * curl -i -X GET 'http://feed.api.mama.com/statuses/friends_timeline?app_id=1&uid=1'
 */
$app->get('/statuses/friends_timeline', array($feedController, 'getFeedListByUid'));

$userController = new UserController();

/**
 * curl -d "uid=1&friend_uid=2" 'http://feed.api.mama.com/friendships/create'
 * curl -d "uid=2&friend_uid=1" 'http://feed.api.mama.com/friendships/create'
 */
$app->post('/friendships/create', array($userController, 'addFollow'));

/**
 * curl -d "uid=1&friend_uid=2" 'http://feed.api.mama.com/friendships/destroy'
 */
$app->post('/friendships/destroy', array($userController, 'unFollow'));

/**
 * curl -i -X GET 'http://feed.api.mama.com/friendships/followers?uid=1'
 */
$app->get('/friendships/followers', array($userController, 'getFansList'));

/**
 * curl -i -X GET 'http://feed.api.mama.com/friendships/friends?uid=2'
 */
$app->get('/friendships/friends', array($userController, 'getFollowList'));
