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
use Phalcon\Di\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Db\Adapter\Pdo\Sqlite as PdoSqlite;
$url = new Url();

// Application configuration data
// -------------------------------
$config['version'] = '0.0.1';
// Setting a full domain as base URI
$url->setBaseUri('http://localhost/pt');

$config['apiurl'] = $url->getBaseUri();

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
    $di->set('dbMysql', function () use ($config){
        return new PdoMysql($config['mysql']);
    });
} else {
    // Set up the database service (sqlite)
    $di->set('dbSqlite', function () use ($config){
        return new PdoSqlite($config['sqlite']);
    });
}

//$app = new Micro();
$app = new \Phalcon\Mvc\Micro($di);
$app->config = $config;
$app->myapi = new stdClass;
$app->myapi->pglimit = 50; #default items per page
$app->myapi->_reqUrl = $app->request->get('_url'); #set default _reqUrl

$app->get('/', function () {
    echo "<h1>Welcome !! </h1>The Json API for Alpine Linux.";
});

$app->get('/about', function () use ($config) {
    $data = array();
    $data['jsonapi'] = array('version' => '1.0');
    $data['meta'] = array (
        'version' => $config['version'],
        'backend' => '',
        'apiurl' => $config['apiurl']
    );
    $data['links'] = array( 'self' => $config['apiurl'].'/about' );
    $data['data'][] = (object)array();
    json_api_encode($data, $this);
});

$app->get('/docs', function () {
    echo "<h1>Api Documents</h1>Todo.";
});

/*

The API consists of the following methods: # TODO - clean text import from lua

  Method  | URL                                    | Action
  ------------------------------------------------------------------------------------------------
  GET     | /$                                     | web.RedirectHandler, {"/packages"}
  GET     | /packages/(.*)/relationships/contents$ | ApiRelationshipsContentRenderer, {aports=aports,model=model}
  GET     | /packages/(.*)/relationships/(.*)$     | ApiPackagesRelationship, {aports=aports,model=model}
  GET     | /packages/(.*)$                        | ApiPackageRenderer {aports=aports,model=model}
  GET     | /packages                              | ApiPackagesRenderer {aports=aports,model=model}
  --contents --------------
  GET     | /contents/(.*)/relationships/packages$ | ApiRelationshipsPackagesRenderer, {aports=aports,model=model}
  GET     | /contents/(.*)$                        | ApiContentRenderer {aports=aports,model=model}
  GET     | /contents                              | ApiContentsRenderer {aports=aports,model=model}
  --static ----------------
  GET     | favicon.ico                            | web.StaticFileHandler, "assets/favicon.ico"
  --others ----------------
  DELETE  | /api/test/100                          | 
  POST    | /api/add/10                            | 
  PUT     | /api/add/10                            | 

*/

// Define routes here

// Retrieves packages
$app->get('/packages', function () use ($app) {

    $data = initJapiData($app, 'packages');

    # get Packages count and figure out paginations
    $limit = $app->myapi->pglimit; $first = "1"; $next = "2";
    $res = Packages::find();
    $tnum = count($res);
    $tpgs = round($tnum/$limit);
    $mod = $tnum%$limit;
    if($mod >= 0) $tpgs = $tpgs+1;

    $offset = isset($app->myapi->offset) ? $app->myapi->offset : 0;

    $data->meta = array(
        'total-pages' => $tpgs,
        'per-page' => $app->myapi->pglimit,
        'count' => $tnum
    );

    $_reqUrl = cleanUri($app->request->get('_url'));
    //$app->myapi->_reqUrl = $_reqUrl;

    if( isset($app->myapi->offset) ) {
      $slink = preg_replace('#\/page.*$#', '', $_reqUrl);
      $next = $app->myapi->pgNext;
      $app->myapi->_reqUrl = $slink;
    } else {
      $slink = $_reqUrl;
    }
    $slink = $app->config['apiurl'] . $slink;

    $data->links = (object)array();
    $data->links->self = $app->config['apiurl'] . $_reqUrl;
    $data->links->next = $slink.'/page/'.$next;
    $data->links->last = $slink.'/page/'.$tpgs;
    $data->links->first = $slink.'/page/'.$first;

    $res = Packages::find(
        array(
            "order" => "id DESC",
            "limit" => $limit,
            "offset" => "$offset"
        )
    );
    $data->data = fmtData($res, 'packages.', $app)->data;
    $data = populate_maintainer($data, $app);

    if($data) json_api_encode($data, $app);

});

// Retrieves packages by paginations (defaults)
$app->get('/packages/page', function () use ($app) {
    $app->myapi->offset = 0;
    $app->myapi->pgNext = 2;
    $app->myapi->pgPrev = 1;
    $app->handle("/packages");
});

// Retrieves packages by paginations
$app->get('/packages/page/{page:[0-9]+}', function ($page) use ($app) {
    $page = (int)$page;
    $app->myapi->offset = $page * $app->myapi->pglimit + 1;
    $app->myapi->pgNext = $page + 1;
    $app->myapi->pgPrev = $app->myapi->offset - 1;
    $app->handle("/packages");
});

// Retrieves packages by id
$app->get('/packages/{id:[0-9]+}', function ($id) use ($app) {
    $data = initJapiData($app, 'packages');

    $res = Packages::find( array( "id = '$id'", 'limit' => 1) );
    $data->data = fmtData($res, 'packages.id', $app)->data;
    $data = populate_maintainer($data, $app);
    if($data) json_api_encode($data, $app);
});

// Retrieves packages by relationships
$app->get('/packages/{id:[0-9]+}/relationships/{type}', function ($id, $type) use ($app) {

    $rels = array ("origins", "depends", "provides", "install_if", "contents");

    if($type === 'origins') {
        return $app->handle("/packages/$id");
    }

    if($type === 'depends') {
        return $app->handle("/depends/pid/$id");
    }

    if($type === 'provides') {
        return $app->handle("/provides/pid/$id");
    }

    if($type === 'install_if') {
        return $app->handle("/install_if/pid/$id");
    }

    if($type === 'contents') {
        return $app->handle("/contents/pid/$id");
    }

    $app->handle('/404');

});

$app->get('/install_if/pid/{pid:[0-9]+}', function ($pid) use ($app) {
    $data = initJapiData($app, 'install_if');
    # meta
    $res = Installif::find();
    $tnum = count($res);
    $data->meta = array(
        'total-files' => $tnum
    );
    # data
    $res = Installif::find( array( "pid = '$pid'") );
    $tnum2 = count($res);
    if( ! $tnum2 > 0) return $app->handle('/404');
    $data->meta['pkg-count'] = $tnum2;
    $data->data = fmtData($res, 'install_if.pid', $app)->data;
    return json_api_encode($data, $app);
});

$app->get('/provides/pid/{pid:[0-9]+}', function ($pid) use ($app) {
    $data = initJapiData($app, 'provides');
    # meta
    $res = Provides::find();
    $tnum = count($res);
    $data->meta = array(
        'total-files' => $tnum
    );
    # data
    $res = Provides::find( array( "pid = '$pid'") );
    $tnum2 = count($res);
    if( ! $tnum2 > 0) return $app->handle('/404');
    $data->meta['pkg-count'] = $tnum2;
    $data->data = fmtData($res, 'provides.pid', $app)->data;
    return json_api_encode($data, $app);
});

$app->get('/depends/pid/{pid:[0-9]+}', function ($pid) use ($app) {
    $data = initJapiData($app, 'depends');
    # meta
    $res = Depends::find();
    $tnum = count($res);
    $data->meta = array(
        'total-files' => $tnum
    );
    # data
    $res = Depends::find( array( "pid = '$pid'") );
    $tnum2 = count($res);
    if( ! $tnum2 > 0) return $app->handle('/404');
    $data->meta['pkg-count'] = $tnum2;
    $data->data = fmtData($res, 'depends.pid', $app)->data;
    return json_api_encode($data, $app);
});

// Retrieves package data by its depends(name) relationships (funny relationships)
//  possibly taken as packages that depends on this given named pkg # TODO
$app->get('/depends/{name:[a-z]+.*}/relationships/{type}', function ($name, $type) use ($app) {
    $data = initJapiData($app, 'depends');

    if($type === 'packages') {
        $res = Depends::find( array( "name = '$name'") );
        if( ! count($res) > 0) return $app->handle('/404');
        $pid = $res[0]->pid;

        # ---------------------
        foreach($res as $d) {
            $a[] = $d->pid;
        }
        $l = trim(implode(',', array_unique($a)), ',');
        $l = preg_replace('#\,{2}+#', ',', $l);
        $phql = "SELECT * from Packages where id in ($l) ";
        $res = $app->modelsManager->executeQuery($phql);
        $tnum = count($res);
        $data->meta = array(
            'pkg-count' => $tnum
        );

        $data->data = fmtData($res, 'packages.', $app)->data;
        $data = populate_maintainer($data, $app);

        return json_api_encode($data, $app);
        # ---------------------

        //return $app->handle("/packages/$pid"); # TODO
    }
    $app->handle('/404');
});

// Retrieves contents by id
$app->get('/contents/pid/{pid:[0-9]+}', function ($pid) use ($app) {
    $data = initJapiData($app, 'contents');
    # meta
    $res = Files::find();
    $tnum = count($res);
    $data->meta = array(
        'total-files' => $tnum
    );
    # data
    $res = Files::find( array( "pid = '$pid'") );
    $tnum2 = count($res);
    if( ! $tnum2 > 0) $app->handle('/404');
    $data->meta['pkg-count'] = $tnum2;
    $data->data = fmtData($res, 'contents.pid', $app)->data;
    return json_api_encode($data, $app);
});

// Retrieves contents by id
$app->get('/contents/id/{id:[0-9]+}', function ($id) use ($app) {
    $data = initJapiData($app, 'contents');
    $res = Files::find( array( "id = '$id'", 'limit' => 1) );
    if( ! count($res) > 0) return $app->handle('/404');
    $data->data = fmtData($res, 'contents.id', $app)->data;
    return json_api_encode($data, $app);
});

// Retrieves package data by its content(id) relationships
$app->get('/contents/{id:[0-9]+}/relationships/{type}', function ($id, $type) use ($app) {
    if($type === 'packages') {
        $res = Files::find( array( "id = '$id'", 'limit' => 1) );
        $pid = $res[0]->pid;
        return $app->handle("/packages/$pid");
    }
    $app->handle('/404');
});


$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    //$slink = $app->config['apiurl'].$app->request->get('_url');
    $data['jsonapi'] = array('version' => '1.0');
    $data['error'][] = array(
        'status' => '404',
        'source' => (object)array(
                        'pointer' => $app->request->get('_url'),
                        //"parameter" => "include",
                    ),
        'title' => '404 - Page Not Found',
        'detail' => 'This is crazy, but this page was not found!'
    );

    json_api_encode($data, $app);
});


$app->get('/say/welcome/{name}', function ($name) { #for testing
    echo "<h1>Welcome $name!</h1>";
});


/*
 Utility functions
 -----------------
*/

function cleanUri($_reqUrl) {
    //$_reqUrl = $app->request->get('_url');
    $pat = array('#\/{2}+#', '#\/{1}+$#');
    $rep = array('/', '');
    return preg_replace($pat, $rep, $_reqUrl);
}

function fmtMaintainer($d) {
    return $d['name'].' <'.$d['email'].'>';
}

function populate_maintainer($data, $app) { # move to model # TODO
    // add maintainer into object data
    //  using array method, rather than table join as in sql query
    foreach($data->data as $d) {
        $a[] = $d->attributes->maintainer;
    }
    $l = trim(implode(',', array_unique($a)), ',');
    $l = preg_replace('#\,{2}+#', ',', $l);
    if(empty($l)) return $data;
    $phql = "SELECT * from Maintainer where id in ($l) ";
    $res2 = $app->modelsManager->executeQuery($phql);
    if( ! count($res2) > 0 ) return $data;
    $m = array();
    foreach($res2 as $m1) {
        $m[$m1->id]['name'] = $m1->name;
        $m[$m1->id]['email'] = $m1->email;
    }
    foreach($data->data as $d) {
        $n = (int)$d->attributes->maintainer;
        if( $n >= 1 ) {
            $d->attributes->maintainer = fmtMaintainer($m[$n]);
        }
    }
    return $data;
}

function initJapiData($app, $type='') {
    $data = (object)array();
    # misc top level json api objects (non standards)
    $data->jsonapi = array('version' => '1.0');
    $data->meta = (object)array();

    $_reqUrl = cleanUri($app->request->get('_url'));
    $data->links = (object)array();
    $data->links->self = $app->config['apiurl'] . $_reqUrl;
    return $data;
}

# TO CLEAN ( repeating codes ), use model if better
function fmtData($res, $type, $app) {
    if ( ! $res ) { $app->handle('/404'); }

    list($type, $subtype) = explode('.', $type);
    $jsonApi = (object)array();

    if($type === 'install_if') {
        $list = array( # hard code list just to remove pid field # TODO
             "name", "version", "operator"
        );
        $slink = '/' . $type . '/'; # TODO

        foreach ($res as $item) {
            $obj = (object)array();
            $obj->pid = $item->pid;
            $obj->type = $type;
            $obj->links = new stdClass;

            foreach($list as $l) {
                $newitem[$l] = $item->$l;
            }
            $obj->attributes = (object)$newitem;
            $jsonApi->data[] = $obj;
            # using pid would make url name, either add id columns to table
            #  or deal with weird file names
            $obj->links->self = $slink.$item->name;
            $rlink = $slink.$item->name .'/relationships/';

            // some cleaning
            $obj->links->self = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $obj->links->self);
            $rlink = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $rlink);

            # relationships objects
            $rels['packages']['links']['self'] = $rlink.'packages';
            $obj->relationships = (object)$rels;
        }
        return $jsonApi;
    }

    if($type === 'provides') {
        $list = array( # hard code list just to remove pid field # TODO
             "name", "version", "operator"
        );
        $slink = '/' . $type . '/';
        //$slink = '/' . 'files' . '/'; # TODO

        foreach ($res as $item) {
            $obj = (object)array();
            $obj->pid = $item->pid;
            $obj->type = $type;
            $obj->links = new stdClass;

            foreach($list as $l) {
                $newitem[$l] = $item->$l;
            }
            $obj->attributes = (object)$newitem;
            $jsonApi->data[] = $obj;
            # using pid would make url name, either add id columns to table
            #  or deal with weird file names
            $obj->links->self = $slink.$item->name;
            $rlink = $slink.$item->name .'/relationships/';

            // some cleaning
            $obj->links->self = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $obj->links->self);
            $rlink = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $rlink);

            # relationships objects
            $rels['packages']['links']['self'] = $rlink.'packages';
            $obj->relationships = (object)$rels;
        }
        return $jsonApi;
    }

    if($type === 'depends') {
        $list = array( # hard code list just to remove pid field # TODO
             "name", "version", "operator"
        );
        $slink = '/' . $type . '/';
        //$slink = '/' . 'files' . '/'; # TODO

        foreach ($res as $item) {
            $obj = (object)array();
            $obj->pid = $item->pid;
            $obj->type = $type;
            $obj->links = new stdClass;

            foreach($list as $l) {
                $newitem[$l] = $item->$l;
            }
            $obj->attributes = (object)$newitem;
            $jsonApi->data[] = $obj;
            # using pid would make url name, either add id columns to table
            #  or deal with weird file names
            $obj->links->self = $slink.$item->name;
            $rlink = $slink.$item->name .'/relationships/';

            // some cleaning
            $obj->links->self = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $obj->links->self);
            $rlink = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $rlink);

            # relationships objects
            $rels['packages']['links']['self'] = $rlink.'packages';
            $obj->relationships = (object)$rels;
        }
        return $jsonApi;
    }

    if($type === 'contents') {
        $list = array( # hard code list just to remove pid field # TODO
             "file", "path"
        );
        $slink = '/' . $type . '/';

        foreach ($res as $item) {
            $obj = (object)array();
            $obj->id = $item->id;
            $obj->type = $type;
            $obj->links = new stdClass;

            foreach($list as $l) {
                $newitem[$l] = $item->$l;
            }
            $obj->attributes = (object)$newitem;
            if( $subtype === 'pid' ) {
            } else {
            }
            $jsonApi->data[] = $obj;
            $obj->links->self = $slink.$item->id;
            $rlink = $slink.$item->id .'/relationships/';

            // some cleaning
            $obj->links->self = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $obj->links->self);
            $rlink = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $rlink);

            # relationships objects
            $rels['packages']['links']['self'] = $rlink.'packages';
            $obj->relationships = (object)$rels;
        }
        return $jsonApi;
    }

    if($type === 'packages') {
        $list = array( # hard code list just to remove id field # TODO
             "license", "arch", "build_time", "maintainer", "checksum",
             "version", "installed_size", "branch", "size", "commit",
             "origin", "url", "repo", "name", "description"
        );

        //$_reqUrl = $app->request->get('_url');
        //$_reqUrl = $app->myapi->_reqUrl;
        //$reqUrl = preg_replace('#\/relationships\/.*$#', '', $_reqUrl);
        //$slink = $app->config['apiurl'].$reqUrl;
        $slink = '/' . $type . '/';

        foreach ($res as $item) {
            $obj = (object)array();
            $obj->id = $item->id;
            $obj->type = $type;
            $obj->links = new stdClass;

            foreach($list as $l) {
                $newitem[$l] = $item->$l;
            }
            $obj->attributes = (object)$newitem;
            if( $subtype === 'id' ) {
                //$obj->links->self = $reqUrl; # to delete
                //$jsonApi->data = $obj; # initially used for single data
                $jsonApi->data[] = $obj; # see http://jsonapi.org/format/ if still an issue
            } else {
                //$obj->links->self = $slink.$obj->id; # to delete
                $jsonApi->data[] = $obj; # for many packages objects
            }
            $obj->links->self = $slink.$item->id;
            $rlink = $slink.$item->id .'/relationships/';

            // some cleaning
            $obj->links->self = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $obj->links->self);
            $rlink = $app->config['apiurl'].preg_replace('#\/{2}+#', '/', $rlink);

            # relationships objects
            $rels['origins']['links']['self'] = $rlink.'origins';
            $rels['depends']['links']['self'] = $rlink.'depends';
            $rels['install_if']['links']['self'] = $rlink.'install_if';
            $rels['provides']['links']['self'] = $rlink.'provides';
            $rels['contents']['links']['self'] = $rlink.'contents';
            $obj->relationships = (object)$rels;
        }
        return $jsonApi;
    }

    $app->handle('/404');
}

function json_api_encode($data, $app, $flags=array()) {
    $header['japi'] = 'application/vnd.api+json';
    //$response = new Phalcon\Http\Response();

    //$app->response->setContentType($header['japi'])->sendHeaders();
    echo json_encode($data);
}


$app->handle();
