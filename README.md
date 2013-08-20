#cbi-php

##Installation

Clone the cbi-php repository

<pre><code>git clone git@github.com:cbinsights/cbi-php.git</code></pre>

Include the PHP class in your script

<pre><code>require('/path-to-file/cbi-php/lib/API.php');</code></pre>

##Example

<pre><code>//include API.php
require('/path-to-file/cbi-php/lib/API.php');

//create an API instance
$api = new API("1234", "56789", true, 'json'); //sandbox API instance
$api = new API("1234", "56789", false, 'json'); //production API instance

//Make an API call
$json_res = $api->Call("https://api1.cbinsights.com/api/1/company/info/?ids=62988,8427");

//convert JSON to PHP array
$array_res = json_decode($json_res, true);
</code></pre>

##Documentation

Detailed API documentation can be found at http://www.cbinsights.com/developers/api/


##License

The MIT License (MIT)

Copyright (c) 2013 CB Insights

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
