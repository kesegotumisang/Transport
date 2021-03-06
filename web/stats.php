<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

date_default_timezone_set('Europe/Zurich');

// init
$app = new Silex\Application();

// default config
$app['redis.config'] = false; // array('host' => 'localhost', 'port' => 6379);

/// load config
$config = __DIR__.'/../config.php';
if (stream_resolve_include_path($config)) {
	include $config;
}

// twig
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'       => __DIR__.'/../views',
));

if ($app['redis.config']) {
	$app['redis'] = new Predis\Client($app['redis.config']);
	$app['stats'] = new Transport\Statistics($app['redis']);

	// home
	$app->get('/', function(Request $request) use ($app) {

	    $calls = $app['stats']->getCalls();
	    $errors = $app['stats']->getErrors();

	    // transform to comma and new line separated list
	    $data = array();
	    foreach (array_slice($calls, -30) as $date => $value) {
	        $data[$date] = array('date' => $date, 'calls' => ($value ?: 0), 'errors' => 0);
	    }
	    foreach (array_slice($errors, -30) as $date => $value) {
	        if (isset($data[$date])) {
	            $data[$date]['errors'] = ($value ?: 0);
	        }
	    }
	    foreach ($data as $key => $value) {
	        $data[$key] = implode(',', $value);
	    }
	    $data = implode('\n', $data);

        // get top resources, stations and errors
        $resources = $app['stats']->getTopResources();
        $stations = $app['stats']->getTopStations();
        $errors = $app['stats']->getTopExceptions();

        // CSV response
        if ($request->get('format') == 'csv') {
            $csv = "Date,Calls\n";
            foreach ($calls as $date => $count) {
                $csv .= "$date,$count\n";
            }
            return new Response($csv, 200, array('Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment;filename=transport.csv'));
        }

        // JSON response
        if ($request->get('format') == 'json') {
            return $app->json(array('calls' => $calls));
        }

	    return $app['twig']->render('stats.twig', array(
	        'data' => $data,
	        'calls' => $calls,
	        'resources' => $resources,
	        'stations' => $stations,
	        'errors' => $errors,
	    ));
	});
} else {
    $app->get('/', function(Request $request) use ($app) {
        return 'No Redis configured. See section "Statistics" in README.md.';
    });
}


// run
$app->run();
