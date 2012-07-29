## Gravatar Helper

This is a really simple Gravatar helper class for use with CodeIgniter.  It makes links which obey the Gravatar defaults rather than provide its own defaults like the current library.

### Basic Usage

To load the helper, drop gravatar_helper.php into helpers, and then:

``` php
<?php
$this->load->helper('gravatar');
```

To use this as a [spark](http://getsparks.org/), you can just do:

``` bash
$ php tools/spark install gravatar_helper
```

To use the helper, use these to generate image links:

``` php
<?php
Gravatar_helper::from_email('john.crepezzi@gmail.com');
Gravatar_helper::from_hash(md5('john.crepezzi@gmail.com')); // if you only have the hash
```

And to add some options (you can make any of these null to default to the gravatar defaults):

``` php
<?php
// email address, rating, size (square), and default image
Gravatar_helper::from_email('john.crepezzi@gmail.com', 'X', 80, 'http://images.com/image.jpg');
```

Or get the profile:

``` php
<?php
Gravatar_helper::profile_from_email('john.crepezzi@gmail.com');
Gravatar_helper::profile_from_hash(md5('john.crepezzi@gmail.com'));
```

### Author

John Crepezzi

### Issues?

Use the built in GitHub issue tracker.

### License

(The MIT License)

Copyright © 2010 John Crepezzi

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the ‘Software’), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED ‘AS IS’, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE
