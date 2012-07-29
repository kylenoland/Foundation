# CodeIgniter Spark Development Kit

This is a spark that will assist in developing other sparks. Most notably, it will
provide spark developers with a tool the sparks they've written before they're
submitted to getsparks.org.

It is the exact library used on the server side to decide whether your spark
passes or fails inspection when it's submitted. Previously, spark developers
would simply get an annoying email telling them what was wrong, and to resubmit.

## Installing This SDK

You should generally use the [spark system](http://getsparks.org/get-sparks)
to install spark-sdk. From the terminal, and inside the root of a CodeIgniter
project, type:

`php tools/spark install spark-sdk`

You now have the exact validation tool that GetSparks.org uses to validate your
submitted spark, installed in sparks/spark-sdk/1.0/.

## Validating A Spark

Let's say you're writing a new spark called 'myspark'. It's located in
sparks/myspark/1.0/.

From our shell, we'll run this on OSX or linux:

`$ php sparks/spark-sdk/1.0/scripts/validate-spark.php sparks/myspark\1.0`

On Windows:

`> php sparks\spark-sdk\1.0\scripts\validate-spark.php sparks\myspark\1.0`

This will run the spark validation script for the myspark, and output any errors that might be present. The only thing it does not currently do is verify the existence of dependency sparks on GetSparks.org.

## The Future

This spark may soon be used for more than simple spark validation.

## Maintainer

Maintain by Kenny Katzgrau <katzgrau@gmail.com> / Twitter: @\_kennyk\_