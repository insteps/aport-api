<?php
/**
 * @category  PHP
 * @author    V.Krishn <vkrishn4@gmail.com>
 * @copyright Copyright (c) 2016 V.Krishn <vkrishn4@gmail.com>
 * @license   GPL
 * @link      http://github.com/insteps/aport-api
 * @version   0.0.1
 *
 */

use Phalcon\Loader;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\Micro;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Db\Adapter\Pdo\Sqlite as PdoSqlite;
$time_start = microtime_float();

// Application configuration data
// -------------------------------
$config['version'] = '0.0.1';

# Setting a full domain as base URI
# cPhalcon use '_url=' to pass request, 
#  eg. http://localhost/aport-api/?_url=
# use this if .htaccess or url rewrite is unavailable.
$config['baseUri'] = 'http://localhost/aport-api';

// for mysql
$config['mysql'] = array(
    "host" =>     "localhost",
    "username" => "root",
    "password" => "",
    "dbname" =>   "aports"
);
$config['mysql']["persistent"] = false;

// for sqlite
$config['sqlite'] = array(
    "dbname" => "db/aports.db"
);

$config['dbtype'] = 'sqlite';

# default items per page
$config['app']['pglimit'] = 50;

$url = new Url();
$url->setBaseUri($config['baseUri']);
$config['apiurl'] = $url->getBaseUri();
// -------------------------------

// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();

$loader->registerDirs(
    array(
        __DIR__ . '/models/'
    )
)->register();

$di = new FactoryDefault();

if( $config['dbtype'] === 'mysql' ) {
    // Set up the database service (mysql)
    $di->set('dbMysql', function() use ($config){
        return new PdoMysql($config['mysql']);
    });
} else {
    // Set up the database service (sqlite)
    $di->set('dbSqlite', function() use ($config){
        return new PdoSqlite($config['sqlite']);
    });
}


// Create a events manager
$eventsManager = new EventsManager();

// Listen all the application events
$eventsManager->attach('micro', function($event, $app) {
    if ($event->getType() === 'beforeExecuteRoute') {
    }
});


// Start application
// -------------------------------

//$app = new Micro();
$app = new \Phalcon\Mvc\Micro($di);
$app->config = $config;
$app->myapi = new stdClass;
$app->myapi->time_start = $time_start;
$app->myapi->pglimit = $config['app']['pglimit'];
$app->myapi->_reqUrl = $app->request->get('_url'); #set default _reqUrl

// Bind the events manager to the app
$app->setEventsManager($eventsManager);

$app->before(function() use ($app) {
    // This is executed when the request has been served
    $_reqMethod = $app->request->getMethod();
    //$_url = $app->request->get('_url');
    //throw new \Exception("An error");
    //print_r($_GET);
    //return false;
});



// Define routes here
// ====================

/*

The Site related API consists of the following methods:
  Method  | URL                                    | Action
  ------------------------------------------------------------------------------------------------
  GET     | /$                                     | Welcome message
  GET     | /about$                                | About sites api service
  GET     | /docs$                                 | Documents welcome page
  GET     | /docs/(.*)$                            | Documents subpages

*/

$app->get('/', function() {
    echo "<h1>Welcome !! </h1>The Json API for Alpine Linux aports.\n";
});

$app->get('/about', function() use ($app, $config) {
    $data = initJapiData($app, 'about');
    $data->meta = array(
        'version' => $config['version'],
        'backend' => '',
        'apiurl' => $config['apiurl']
    );
    $data->data[] = (object)array();
    json_api_encode($data, $app);
});

$app->get('/docs', function() {
    echo "<h1>Api Documents</h1>Todo.\n";
});

/*

The Aports API consists of the following methods: # TODO - clean text import from lua

  Method  | URL                                    | Action
  ------------------------------------------------------------------------------------------------
  GET     | /$                                     | Json api welcome page
  GET     | /packages/(.*)/relationships/contents$ | Packages->Content relationships, {pid}
  GET     | /packages/(.*)/relationships/(.*)$     | Packages relationship, {pid} {install_if|provides|depends|contents|flagged}
  GET     | /packages/(.*)$                        | ApiPackageRenderer {pid}
  GET     | /packages                              | ApiPackagesRenderer - default page, new pkg on top
  GET     | /packages/page/<num>                   | Paginated object for packages
  --contents --------------
  GET     | /contents/(.*)/relationships/packages$ | Content->Packages relationships, {id}
  GET     | /contents/(.*)$                        | ApiContentRenderer {id}
  GET     | /contents                              | ApiContentsRenderer
  --static ----------------
  GET     | /assets/*                              | static files, eg. "assets/favicon.ico"
  --others ----------------
  DELETE  | /api/test/100                          | ...
  POST    | /api/add/10                            | ...
  PUT     | /api/add/10                            | ...

*/


/*
  Search by GET
  Search filters are split into key/value pairs, order/presence in uri are optional,
  following keys/values are recognized
  1. category/branch:repo:arch eg. category/v3.4:main:x86 ('all' keyword for any)
     defaults to category/edge:main:x86 if error in keywords
  2. name/<pkgname> ( wildcard recognized '_' )
  3. maintainer/<maintainerName>  ( wildcard recognized '_' )
  4. flagged/[yes|no]
*/
$app->get('/search/{where:[a-z0-9\_]+}{filters:.*}', function($where, $filters) use ($app) {
    $data = initJapiData($app, 'search');

    $_w = array('packages', 'contents');
    //$_k = array('category', 'name', 'maintainer', 'flagged'); # TODO
    if ( ! in_array($where, $_w)) return;

    //$filter = (array)sanitize_filters($filters, $where, $app);
    $f = explode('/', trim(single_slash(urldecode($filters)), '/'));
    for($c=0; $c<=count($f); $c=$c+2) { # limit key/value to 56 chars each
        if($f[$c]) $filter[mb_substr(@$f[$c], 0, 56)] = mb_substr(@$f[$c+1], 0, 56);
    }
    unset($f);
    $filter = get2filter($filter);

    $filter['filter'] = array();
    # Create customs filters # TODO
    $filter = set_search_category($filter);
    $filter = set_search_flagged($filter);
    $filter = set_search_maint($filter);
    $filter = set_search_row($filter, $app);

    if(isset($filter['page'])) $app->myapi->reqPage = (int)$filter['page'];

    if('packages' === $where) {
      $filter = set_search_name_pkg($filter);
      $filter = set_search_orderby_pkg($filter);
      $data = get_package($filter, $data, $app);
    }

    $data->meta['search'] = $filter['filter'];
    $data->meta['per-page'] = '<=50';
    $data->meta['count'] = count($data->data);
    if($data) json_api_encode($data, $app);

});

/*
  Advance/Complex Search by POST (expects simple key/value pairs in post data)
  eg. '{"name":"bas_","category":"edge:main:x86"}'
  More complex keywords like 'include' and 'filter for columns' maybe implemented
*/
$app->post('/search/{where:[a-z0-9\_]+}', function($where) use ($app) {
    $data = initJapiData($app, 'search');

    $filter = array();
    #expect simple key/value pairs
    $f = (array)$app->request->getJsonRawBody();
    foreach($f as $k=>$v) { # limit key/value to 56 chars each
        if($v !== '') $filter[mb_substr($k, 0, 56)] = mb_substr($v, 0, 56);
    }
    unset($f);

    $filter['filter'] = array();
    # Create customs filters # TODO
    $filter = set_search_category($filter);
    $filter = set_search_flagged($filter);
    $filter = set_search_maint($filter);
    $filter = set_search_row($filter, $app);
    //print_r($filter); exit;

    if('packages' === $where) {
      $filter = set_search_name_pkg($filter);
      $filter = set_search_orderby_pkg($filter);
      $data = get_package($filter, $data, $app);
    }

    if('files' === $where) {
      //$data = get_file($filter, $data, $app); # TODO
    }

    $data->meta['search'] = $filter['filter'];
    $data->meta['per-page'] = '<=50';
    $data->meta['count'] = count($data->data);
    if($data) json_api_encode($data, $app);

});

$app->post('/search/{where:[a-z0-9\_]+}/page/{page:[0-9]+}', function($where, $page) use ($app) {
    $app->myapi->reqPage = (int)$page;
    $app->handle("/search/$where");
});

// Sanitizes and makes filters into key=>value array
function sanitize_filters($filters='', $where='', $app) {
    return $filter;
}

function set_search_row($f=array(), $app) {
    if( ! array_key_exists('row', $f) ) return $f;
    $rec = explode('-', $f['row']);
    $f['limit'] = ((int)$rec[0] >= 1) ? (int)substr($rec[0], 0, 2) : 0;
    $f['offset'] = ((int)$rec[1] >= 1) ? (int)substr($rec[1], 0, 10) : 0;
    
    $app->myapi->pglimit = $f['limit']>=51 ? $app->myapi->pglimit : $f['limit'];
    $f['filter']['limit'] = implode(',', array($f['limit'], $f['offset']));
    return $f;
}

function get2filter($f=array()) {
    $_k = array('category', 'branch', 'repo', 'arch',
                'name', 'maintainer', 'flagged', 'sort', 'row');
    foreach($_k as $v) {
        if(array_key_exists($v, $_GET)) $f[$v] = mb_substr($_GET[$v], 0, 56);
    }
    return $f;
}

function set_search_category($f) {
    $t = array('branch', 'repo', 'arch');
    $tdef = array('edge', 'main', 'x86_64'); #defaults
    if(isset($f['branch']) || isset($f['repo']) || isset($f['arch'])) {
        foreach($t as $v) { $f[$v] = isset($f[$v]) ? $f[$v] : 'all'; }
        $f['category'] = $f['branch'].':'.$f['repo'].':'.$f['arch'];
    }
    if( ! array_key_exists('category', $f) ) return $f;
    $f['category'] = preg_replace('#[^\w\d\:\_\-\.]#', '', $f['category']);

    $f['all_category'] = get_all_category();
    $cat = explode(':', $f['category']);
    foreach($t as $t0=>$t1) {
       if($cat[$t0] === 'all') continue; 
       $cat2[$t1] = in_array($cat[$t0], $f['all_category'][$t1]) ? $cat[$t0] : $tdef[$t0];
    }
    foreach($cat2 as $t0=>$t1) {
        $f['filter2'][] = "$t0 = '$t1'";
    }
    $f['filter'] = $cat2;
    return $f;
}
function set_search_glob($f, $n, $v, $isCond=1) {
    $len = strlen($f[$n]);
    $l1 = $f[$n]{0} === '_' ? '%' : '';
    $l2 = $f[$n]{$len-1} === '_' ? '%' : '';
    $op = ($l1 === '%' || $l2 === '%') ? 'LIKE' : '=';
    if($isCond) $f['filter2'][] = "$n $op '$l1$v$l2'";
    $f['filter'][$n] = $l1.$v.$l2;
    return $f;
}
function set_search_name_pkg($f) {
    if( ! array_key_exists('name', $f) ) return $f;
    $name = preg_replace('#[^a-z0-9\-\_\.]#', '', $f['name']);
    return set_search_glob($f, 'name', $name);
}
function set_search_maint($f) {
    if( ! array_key_exists('maintainer', $f) ) return $f;
    # add char sanitizing # TODO
    $f = set_search_glob($f, 'maintainer', $f['maintainer'], 0);
    $_f = $f['filter']['maintainer'];

    $condt = " name LIKE '$_f' ";
    $params = array(
            'conditions' => "$condt",
            'columns' => 'id',
            'order' => "id ASC",
           );
    $res = Maintainer::find( $params );

    foreach($res as $d) { $a[] = $d->id; }
    $l = array2csv($a);
    if( ! empty($l)) $f['filter2'][] = "maintainer IN ($l)";

    return $f;
}
function set_search_flagged($f) {
    if( ! array_key_exists('flagged', $f) ) return $f;
    $tdef = array('yes', 'no', ''); #accepted values
    $flagged = in_array($f['flagged'], $tdef) ? $f['flagged'] : '';
    $f['filter']['flagged'] = $flagged;
    if('yes' === $flagged) { # only need 'yes' i.e flagged items for api
        $f['filter2'][] = "fid IS NOT NULL";
    }
    return $f;
}
// only simple order/sort for now
// eg. sort/name:asc or ?sort=name:asc
function set_search_orderby_pkg($f) {
    $f['filter']['sort'] = "id DESC";
    if( ! array_key_exists('sort', $f) ) return $f;
    list( $fld, $or ) = explode(':', $f['sort']);
    $fields = array('id', 'name', 'maintainer', 'build_time',
                     'fid', 'arch','branch', 'repo');
    $order = array('asc', 'desc');
    $fld = in_array($fld, $fields) ? $fld : 'id';
    $or = in_array(@$or, $order) ? $or : 'DESC';
    $f['filter']['sort'] = "$fld $or";
    return $f;
}

// Retrieves all categories
$app->get('/categories', function() use ($app) {
    $data = initJapiData($app, 'categories'); #maynot comply jsonapi spec
    $data->data = get_all_category();
    if($data) json_api_encode($data, $app);
});

// Retrieves packages
$app->get('/packages', function() use ($app) {
    $data = initJapiData($app, 'packages');

    $data = get_package(array(), $data, $app);

    if($data) json_api_encode($data, $app);

});

function get_package($filter=array(), $data=array(), $app) {
    $condt = implode(' AND ', @$filter['filter2']);
    $sort = isset($filter['filter']['sort']) ? $filter['filter']['sort'] : "id DESC";

    # get Packages count
    $params = array(
            'conditions' => "$condt"
           );
    $res = Packages::find( $params );
    $tnum = count($res);
    $tnum = (isset($filter['offset']) && $filter['offset']<=$tnum)
             ? $tnum-$filter['offset'] : $tnum;

    setPageLinks('page', $tnum, $data, $app);

    $_ofs = isset($filter['offset']) ? $filter['offset'] : $app->myapi->offset;
    $params = array(
            'conditions' => "$condt",
            'order' => "$sort",
            'limit' => $app->myapi->pglimit,
            'offset' => $_ofs
           );
    $res = Packages::find( $params );

    $data->data = fmtData($res, 'packages.', $app)->data;
    $data = populate_maintainer($data, $app);
    return $data;
}

// Retrieves packages by paginations
$app->get('/packages/page/{page:[0-9]+}', function($page) use ($app) {
    $app->myapi->reqPage = (int)$page;
    $app->handle("/packages");
});

// Retrieves packages by name
$app->get('/packages/{name:[a-z0-9\-\_\.]+}', function($name) use ($app) {
    // Retrieves packages by paginations (defaults)
    if($name === 'page') { return $app->handle("/packages"); }

    // assuming there is no pkg named 'page'
    return $app->handle("/packages/name/$name");
});

$app->get('/packages/name/{name:[a-z0-9\-\_\.]+}', function($name) use ($app) {
    $data = initJapiData($app, 'packages');

    $res = Packages::find( array( "name = '$name'", "order" => "id DESC") );
    $tnum = count($res);
    if($tnum < 1) { $app->handle('/404'); return; }

    $data->meta = array(
        'count' => $tnum
    );
    $data->data = fmtData($res, 'packages.', $app)->data;
    $data = populate_maintainer($data, $app);
    if($data) json_api_encode($data, $app);
});

// Retrieves packages by id
// would override /packages/{name} if name is all digits
// but should pick single pkg with that ID.
// we are not expecting any pgk <names> with digits only, are we ?
$app->get('/packages/{pid:[0-9]+}', function($pid) use ($app) {
    return $app->handle("/packages/pid/$pid");
});

// Retrieves packages by relationships
$app->get('/packages/{id:[0-9]+}/relationships/{type}', function($id, $type) use ($app) {

    $subtype = 'pid';

    if($type === 'flagged') {
        $res = Packages::findFirst( array( "id = '$id'", 'limit' => 1 ) );
        $id = $res->fid;
    }

    return $app->handle("/$type/$subtype/$id");

});

// Retrieves packages by id
$app->get('/packages/pid/{pid:[0-9]+}', function($pid) use ($app) {
    $data = initJapiData($app, 'packages');

    $res = Packages::find( array( "id = '$pid'", 'limit' => 1) );
    $data->data = fmtData($res, 'packages.id', $app)->data;
    $data = populate_maintainer($data, $app);
    if($data) json_api_encode($data, $app);
});

// Retrieves packages by flagged-id
$app->get('/packages/fid/{fid:[0-9]+}', function($fid) use ($app) {
    $data = initJapiData($app, 'packages');

    $res = Packages::find( array( "fid = '$fid'", "order" => "id DESC" ) );
    $tnum = count($res);
    if($tnum < 1) { $app->handle('/404'); return; }

    $data->meta = array(
        'count' => $tnum
    );
    $data->data = fmtData($res, 'packages.', $app)->data;
    $data = populate_maintainer($data, $app);
    if($data) json_api_encode($data, $app);
});

// Retrieves packages data + flagged data (included)
// i.e a Compound Document
$app->get('/packages/flagged', function() use ($app) {
    $data = initJapiData($app, 'packages');

    $condt = "fid IS NOT NULL";

    # get Packages count
    $params = array( 'conditions' => "$condt", "group" => "origin, branch" );
    $res = Packages::find( $params );
    $tnum = count($res);

    setPageLinks('page', $tnum, $data, $app);

    $params = array(
            "conditions" => "$condt",
            'columns' => 'id, origin, branch, repo, maintainer, fid',
            "order" => "fid DESC",
            "group" => "origin, branch",
            "limit" => $app->myapi->pglimit,
            "offset" => $app->myapi->offset
           );
    $res = Packages::find( $params );

    $data->data = fmtData($res, 'packages.flagged', $app)->data;
    $data = populate_maintainer($data, $app);

    # 
    # Associate Flagged data
    # 
    foreach($res as $d) { $a[] = $d->fid; }
    $l = array2csv($a);
    $condt = "fid IN ($l)";
    $params = array(
            "conditions" => "$condt",
           );
    $res2 = Flagged::find( $params );
    foreach($res2 as $m1) {
        $m[$m1->fid] = $m1;
    }

    foreach($data->data as $k=>$d) {
        $n = (int)$d->attributes->fid;
        if( $n >= 1 && $m[$n] ) {
            $data->data[$k]->relationships->flagged['data'][] = array(
                'type' => 'flagged', 'id' => "$n"
            );
            $data->included = fmtData($res2, 'flagged.none', $app)->data;
        }
    }

    if($data) json_api_encode($data, $app);
});


$app->get('/origins/pid/{pid:[0-9]+}', function($pid) use ($app) {
    $res = Packages::findFirst( array( "id = '$pid'", 'limit' => 1 ) );
    $origin = $res->origin;
    return $app->handle("/packages/name/$origin");
});


$app->get('/flagged', function() use ($app) {
    $data = initJapiData($app, 'flagged');

    # get Packages count
    $res = Flagged::find();
    $tnum = count($res);

    $parturi = $app->request->get()['_url'];
    if( $parturi !== '/flagged/new') {
        setPageLinks('page', $tnum, $data, $app);
    }

    $res = Flagged::find(
        array(
            "order" => "created DESC",
            "limit" => $app->myapi->pglimit,
            "offset" => $app->myapi->offset
        )
    );
    $data->data = fmtData($res, 'flagged.', $app)->data;

    if($data) json_api_encode($data, $app);

});

// Retrieves flagged (latest)
$app->get('/flagged/new', function($page) use ($app) {
    $app->myapi->pglimit = 1;
    $app->handle("/flagged");
});

// Retrieves flagged by paginations (defaults)
$app->get('/flagged/page', function($page) use ($app) {
    $app->handle("/flagged");
});

// Retrieves flagged by paginations
$app->get('/flagged/page/{page:[0-9]+}', function($page) use ($app) {
    $app->myapi->reqPage = (int)$page;
    $app->handle("/flagged");
});

$app->get('/flagged/{fid:[0-9]+}', function($fid) use ($app) {
    return $app->handle("/flagged/pid/$fid");
});

$app->get('/flagged/fid/{fid:[0-9]+}', function($fid) use ($app) {
    return $app->handle("/flagged/pid/$fid");
});

$app->get('/flagged/{fid:[0-9]+}/relationships/{type}', function($fid, $type) use ($app) {
    if($type === 'packages') {
        return $app->handle("/packages/fid/$fid");
    }
    $app->handle('/404');
});

// Seems depends would only have a relationship with packages
// this route can be directed to below route
$app->get('/depends/{name:[a-z]+.*}', function($name) use ($app) {
    $app->handle("/depends/$name/relationships/packages");
});


$app->get('/{rel:install_if|provides|depends|contents|flagged}/pid/{pid:[0-9]+}',
    function($rel, $pid) use ($app) {
    # array(name, className, fmtName)
    $rels['install_if'] = array("install_if", 'Installif', 'install_if.pid');
    $rels['provides'] = array("provides", 'Provides', 'provides.pid');
    $rels['depends'] = array("depends", 'Depends', 'depends.pid');
    $rels['contents'] = array("contents", 'Files', 'contents.pid');
    $rels['flagged'] = array("flagged", 'Flagged', 'flagged.fid');
    $_r = $rels[$rel];
    list($type, $subtype) = explode('.', $_r[2]);

    $data = initJapiData($app, $_r[0]);
    # meta
    $res = $_r[1]::find();
    $tnum = count($res);

    # data
    $res = $_r[1]::find( array( "$subtype = '$pid'") );
    $tnum2 = count($res);
    if( ! $tnum2 > 0) return $app->handle('/404');

    //setPageLinks('page', $tnum2, $data, $app);
    $data->meta['total-files'] = $tnum;
    $data->meta['pkg-count'] = $tnum2;

    $data->data = fmtData($res, $_r[2], $app)->data;
    return json_api_encode($data, $app);
});

// Retrieves contents(files) by id
$app->get('/contents/id/{id:[0-9]+}', function($id) use ($app) {
    $data = initJapiData($app, 'contents');
    $res = Files::find( array( "id = '$id'", 'limit' => 1) );
    if( ! count($res) > 0) return $app->handle('/404');
    $data->data = fmtData($res, 'contents.id', $app)->data;
    return json_api_encode($data, $app);
});

// Retrieves package data by its content(files->id) relationships
$app->get('/contents/{id:[0-9]+}/relationships/{type}', function($id, $type) use ($app) {
    if($type === 'packages') {
        $res = Files::find( array( "id = '$id'", 'limit' => 1) );
        $pid = $res[0]->pid;
        return $app->handle("/packages/$pid");
    }
    $app->handle('/404');
});


// Retrieves package data by its depends(name) relationships (funny relationships)
//  possibly taken as packages that depends on this given named pkg # TODO
$app->get('/depends/{name:[a-z]+.*}/relationships/{type}', function($name, $type) use ($app) {
    $data = initJapiData($app, 'depends');

    if($type === 'packages') {
        $res = Depends::find( array( "name = '$name'") );
        if( ! count($res) > 0) return $app->handle('/404');
        $pid = $res[0]->pid;

        # ---------------------
        foreach($res as $d) { $a[] = $d->pid; }
        $l = array2csv($a);
        $condt = "id IN ($l)";
        $params = array( "conditions" => "$condt" );
        $res = Packages::find( $params );
        //$res = Packages::query->where('id IN (:l:)')->bind(array("l" => "1,2"))->execute(); # ??
        $tnum = count($res);
        $data->meta = array(
            'files' => $tnum
        );

        $data->data = fmtData($res, 'packages.', $app)->data;
        $data = populate_maintainer($data, $app);

        return json_api_encode($data, $app);
        # ---------------------

        //return $app->handle("/packages/$pid"); # TODO
    }
    $app->handle('/404');
});


// Retrieves maintainer's names
$app->get('/maintainer/names', function() use ($app) {
    $data = initJapiData($app, 'maintainer');

    # get Packages count
    $res = Maintainer::find();
    $tnum = count($res);
    setPageLinks('page', $tnum, $data, $app);

    $res = Maintainer::find(
        array(
            'columns' => 'id, name',
            "order" => "name ASC",
            "limit" => $app->myapi->pglimit,
            "offset" => $app->myapi->offset
        )
    );

    $data->data = fmtData($res, 'maintainer.names', $app)->data;
    if($data) json_api_encode($data, $app);
});

// Retrieves maintainer's names
$app->get('/maintainer/names/page/{page:[0-9]+}', function($page) use ($app) {
    $app->myapi->reqPage = (int)$page;
    $app->handle("/maintainer/names");
});

# Error Handling / Responses
# --------------------------

# Error $exceptions # TODO
$app->error(
    function($exception) {
        echo "An error has occurred";
    }
);

# Accepted
$app->get('/202', function() use ($app) {
});

# No Content
$app->get('/204', function() use ($app) {
});

# Error Response 401
$app->get('/401', function() use ($app) {
    $app->response->setStatusCode(401, "Unauthorized")->sendHeaders();
    $data = initJapiErrData($app, array(
        '401', 'Access is not authorized', ''
    ));
    json_api_encode($data, $app);
});

# Forbidden
$app->get('/403', function() use ($app) {
});

# Error Response 404
$app->notFound(function() use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    $data = initJapiErrData($app, array(
        '404',
        '404 - Page Not Found',
        'This is crazy, but this page was not found!'
    ));
    json_api_encode($data, $app);
});

# Error Response 406
$app->get('/406', function() use ($app) {
    $app->response->setStatusCode(406, "Not Acceptable")->sendHeaders();
    $data = initJapiErrData($app, array(
        '406', 'Not Acceptable', ''
    ));
    json_api_encode($data, $app);
});

# Error Response 415
$app->get('/415', function() use ($app) {
    $app->response->setStatusCode(415, "Unsupported Media Type")->sendHeaders();
    $data = initJapiErrData($app, array(
        '415', 'Unsupported Media Type', ''
    ));
    json_api_encode($data, $app);
});

# --------------------------

# for testing
$app->get('/say/welcome/{name}', function($name) {
    echo "<h1>Welcome $name!</h1>";
});


/*
 Utility functions
 --------------------------
*/

function microtime_float() {
    list($usec, $sec) = explode(" ", microtime(TRUE));
    return ((float)$usec + (float)$sec);
}

function get_meta_end($data, $app) {
    $time_end = microtime_float();
    $time = number_format($time_end - $app->myapi->time_start, 4);
    $data->meta['elapsed_time'] = "$time seconds";
    $b = memory_get_peak_usage(true);
    $b2 = memory_get_usage(true);
    $data->meta['memory_usage'] = ($b/1024/1024) .' / ' . ($b2/1024/1024) . ' Mb';
    return $data;
}

function getModelsMeta($tbl='', $type='fields') {
    $_k = array('Packages', 'Files', 'Maintainer', 'Flagged', 'Depends');
    if( ! in_array($tbl, $_k, TRUE) ) return false;

    $d = new $tbl();
    $metaData = $d->getModelsMetaData();
    // -------------------------
    if('fields' === $type) {
        // Get table fields names
        $attributes = $metaData->getAttributes($d);
        return ($attributes);
    }
    if('dtypes' === $type) {
        // Get table fields data types
        $dataTypes = $metaData->getDataTypes($d);
        return ($dataTypes);
    }
    return false;
    // -------------------------
}

function isJapiReqHeader($app) { # TODO
    // Get 'Accept:' header
    // ---------------------------
    //$request = new \Phalcon\Http\Request();
    //$_h_accept = $request->getBestAccept(); # getAcceptableContent

    // Get the best acceptable content by the browser. ie text/xml
    $_h_accept = (array)$app->request->getAcceptableContent();

    // Get 'Content-Type:' header
    // ---------------------------
    $_h_ctype = $app->request->getHeader('content-type');

    // No need to process httpd server's extra headers for now
    // eg. 'HTTP_CONTENT_TYPE' set by nginx

    return $_h_ctype;
    if ($_h_accept == "application/vnd.api+json") {
        echo "A json request";
    }

}

function cleanUri($_reqUrl) {
    $pat = array('#\/{2}+#', '#\/{1}+$#');
    $rep = array('/', '');
    return preg_replace($pat, $rep, $_reqUrl);
}

function initJapiData($app, $type='') {
    $data = (object)array();
    # misc top level json api objects (non standards)
    $data->jsonapi = array('version' => '1.0');
    $data->meta = array();

    $_reqUrl = cleanUri($app->request->get('_url'));
    $data->links = (object)array();
    $data->links->self = $app->config['apiurl'] . $_reqUrl;
    return $data;
}

function initJapiErrData($app, $type=array()) {
    //$slink = $app->config['apiurl'].$app->request->get('_url');
    $data = (object)array();
    $data->jsonapi = array('version' => '1.0');
    $data->error[] = array(
        'status' => @$type[0],
        'source' => (object)array(
                        'pointer' => $app->request->get('_url'),
                        //"parameter" => "include",
                    ),
        'title' => @$type[1],
        'detail' => @$type[2]
    );
    return $data;
}

function get_all_category() {
    $t = array('branch', 'repo', 'arch');
    $cats = array();
    # get all Repoversions available
    $res = Repoversion::find();
    foreach($res as $m1) {
        foreach($t as $t1) { $m[$t1][] = $m1->$t1; }
    }
    foreach($t as $t1) { $cats[$t1] = array_values(array_unique($m[$t1])); }
    unset($m, $m1, $res);
    return $cats;
}

function resetPage($app) {
    $app->myapi->reqPage = 1;
    $app->myapi->offset = 0;
    $app->myapi->pgPrev = 1;
    $app->myapi->pgNext = 2;
}

function setPage($page, $tnum, $app) {
    # use Packages count and figure out paginations
    $page = (int)$page;
    $limit = $app->myapi->pglimit;
    $tpgs = floor($tnum/$limit);
    $mod = $tnum%$limit;
    if($mod > 0) $tpgs = $tpgs+1;
    if($page > $tpgs) { $app->handle('/404'); exit; }

    $multiplier = ($page <= 1) ? 0 : $page-1;
    $app->myapi->offset = $multiplier * $app->myapi->pglimit;
    $app->myapi->pgNext = ($page+1 > $tpgs) ? $page : $page+1;
    $app->myapi->pgPrev = ($page-1 <= 0) ? 1 : $page-1;
    $app->myapi->pgTotal = $tpgs;
}

function setPageLinks($uriPart, $tnum, $data, $app) {

    if( (int)$app->myapi->reqPage <= 1 ) {
      resetPage($app); 
    }
    setPage($app->myapi->reqPage, $tnum, $app);

    $data->meta = array(
        'total-pages' => $app->myapi->pgTotal,
        'per-page' => $app->myapi->pglimit,
        'total-count' => $tnum
    );

    $_reqUrl = cleanUri($app->request->get('_url'));
    //$app->myapi->_reqUrl = $_reqUrl;

    if( isset($app->myapi->offset) ) {
      $slink = preg_replace('#\/'.$uriPart.'.*$#', '', $_reqUrl);
      //$next = $app->myapi->pgNext;
      //$app->myapi->_reqUrl = $slink;
    } else {
      $slink = $_reqUrl;
    }
    $slink = $app->config['apiurl'] . $slink;

    $data->links = (object)array();
    $data->links->self = $app->config['apiurl'] . $_reqUrl;
    $data->links->next = $slink."/$uriPart/".$app->myapi->pgNext;
    $data->links->last = $slink."/$uriPart/".$app->myapi->pgTotal;
    $data->links->first = $slink."/$uriPart/1";
}

function fmtMaintainer($d) {
    return $d['name'].' <'.$d['email'].'>';
}

function single_slash($parturi) {
    return preg_replace('#\/{2}+#', '/', $parturi);
}

function array2csv($arr) {
    return preg_replace('#\,{2}+#', ',', trim(implode(',', array_unique($arr)), ','));
}

# Populates the package maintainer field
function populate_maintainer($data, $app) { # move to model # TODO
    // add maintainer into object data
    // using array method, rather than table join as in sql query
    foreach($data->data as $d) {
        $a[] = $d->attributes->maintainer;
    }
    $l = array2csv($a);
    if(empty($l)) return $data;
    $phql = "SELECT * FROM Maintainer WHERE id IN ($l) $f";
    $res2 = $app->modelsManager->executeQuery($phql);
    if( ! count($res2) > 0 ) return $data;
    $m = array();
    foreach($res2 as $m1) {
        $m[$m1->id] = ['name' => $m1->name, 'email' => $m1->email];
    }
    foreach($data->data as $d) {
        $n = (int)$d->attributes->maintainer;
        if( $n >= 1 ) {
            $d->attributes->maintainer = fmtMaintainer($m[$n]);
        }
    }
    return $data;
}

# TO CLEAN ( repeating codes ), use model if better
function fmtData($res, $type, $app) {
    if ( ! $res ) { $app->handle('/404'); }

    list($type, $subtype) = explode('.', $type);
    $jsonApi = (object)array();
    $rels = array("packages");
    $slink = '/' . $type . '/';

    if($type === 'flagged') {
        // identifier
        $idf = 'fid'; $self = 'fid';
    }

    if($type === 'install_if') {
        $idf = 'pid'; $self = 'name';
    }

    if($type === 'provides') {
        $idf = 'pid'; $self = 'name';
        //$slink = '/' . 'files' . '/'; # TODO
    }

    if($type === 'depends') {
        $idf = 'pid'; $self = 'name';
    }

    if($type === 'contents') { # from table 'files'
        $idf = 'id'; $self = 'id';
    }

    if($type === 'packages') {
        $idf = 'id'; $self = 'id';
        $rels = array( "depends", "provides", "install_if",
                       "origins", "contents", "flagged" );
    }

    if($type === 'maintainer') {
        $idf = 'id'; $self = 'id';
        $rels = array();
    }

    if('none' === $subtype) $rels = array();

    foreach ($res as $item) {
        $obj = (object)array();
        $obj->type = $type;
        $obj->id = $item->$idf;
        $obj->links = new stdClass;

        $obj->attributes = (object)$item;

        # see http://jsonapi.org/format/#document-top-level if still an issue
        //$jsonApi->data = $obj; # primary data in a single resource identifier object
        $jsonApi->data[] = $obj; # for more than one object (array)

        # using pid would add name in url, either add id columns to tables
        #  or deal with weird file names

        $obj->links->self = $slink.$item->$self;

        if($type === 'depends') {
            $obj->links->self = '/packages/'.$item->name;
        }

        $rlink = $slink.$item->$self;

        // some cleaning
        $obj->links->self = $app->config['apiurl'].single_slash($obj->links->self);
        //unset($obj->links); // need more rationale # TODO
        $rlink = $app->config['apiurl'].single_slash($rlink.'/relationships/');
        unset($item->$idf);

        if(count($rels) >= 1) {
            # make relationships objects links
            foreach($rels as $val) {
                $_rels[$val]['links']['self'] = $rlink.$val;
            }
            $obj->relationships = (object)$_rels;
        }
    }
    return $jsonApi;

}

function json_api_encode($data, $app, $flags=array()) {
    $header['japi'] = 'application/vnd.api+json';
    //$response = new Phalcon\Http\Response();

    //enable in production
    $app->response->setContentType($header['japi'])->sendHeaders();
    $data = get_meta_end($data, $app);
    echo json_encode($data);
}

// --------------------------
# removes php version numbers, 
# considered as probable security issue
header_remove('X-Powered-By');

$app->handle();
