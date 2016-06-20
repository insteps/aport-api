

JSONAPI implementations for aports packages.


Api is build around packages data and its relationships with other tables in database,
using, simple one-to-one relationships (based on foreign-key/pid/id).

Currently, any JOINS in database is being avoided and maybe used if needed.


Relationships
==============
Following relationships exists, emanating from a base package<name>
* origin (original package to which the ''subpackage/named package'' belongs to)
* install_if 
* provides (what the package provides)
* depends (what the named package depends on)
* contents (contents of the package)
* flagged (if package is flagged, relationship to its data)


Base URL
-----------
https://api.alpinelinux.org/

packages
----------
api uri: `<BaseURL>/packages/...`

Get packages list (paginated 50 items)

* eg. `curl https://api.alpinelinux.org/packages | jq . | less`


searching
-----------
api uri: `<BaseURL>/search/<where>/key1/value1/key2/value2`

1. where = packages|contents
2. keys = category|name|maintainer|flagged

api uri: `<BaseURL>/search/packages/category/<branch>:<repo>:<arch>/name/<pkgName>`
* Searches packages by categories and pkgName
* Reserved keyword in categories - ''all''
* Wildcard for package name recogonized '_'


* eg. Basic search
`curl https://api.alpinelinux.org/search/packages/category/v3.4:all:x86/name/_bas_ | jq . | less`
* eg. Advanced search
`curl -X POST https://api.alpinelinux.org/search/packages -d '{"name":"_bas_","category":"edge:main:x86"}' | jq . | less`


categories
-----------
api uri: `<BaseURL>/categories`

Get categories available.
Categories are for branch:repo:arch (eg. edge:main:x86 )

* eg. Get available categories
`<BaseURL>/categories`


flagged packages
-----------------
1. api uri: `<BaseURL>/flagged`
* Get flagged data (paginated) (follow relationships link to get package data)
* `<BaseURL>/flagged/page/<num>` - more items (older)

2. api uri: `<BaseURL>/flagged/new`
* Get current flagged item

* How to get the current package that got flagged
`new=$(curl api.alpinelinux.org/flagged/new | jq .data[].relationships.packages.links.self | sed -e 's/"//g'); curl $new | jq .data[].attributes.origin | uniq`


maintainers
------------
* Get first page
`<BaseURL>/maintainer/names`
* Get other pages
`<BaseURL>/maintainer/names/page/<num>`


