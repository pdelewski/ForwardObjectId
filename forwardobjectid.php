<?php

// mimics SDK span, contains only $name property
class Span
{
  public string $name;
}

// mimics SDK context, contains $span array
class Context
{
  public $spans = [];
}

// mimics PSR-7 ServerRequest
class BasicRequest
{
    private $attributes = [];

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function withAttribute($attribute, $value): BasicRequest
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;

        return $new;
    }
}

function forwardObjectId(mixed $old_object, mixed $new_object, Context &$context) {
     $old_id = spl_object_id($old_object);
     $span = $context->spans[$old_id];
     unset($context->spans[$old_id]);
     $new_id = spl_object_id($new_object);
     $context->spans[$new_id] = $span;
}

// hooking into withAttribute method
\OpenTelemetry\Instrumentation\hook(BasicRequest::class, 'withAttribute',
   static function (mixed $object, array $params) {
   },
   post:static function (mixed $object, array $params, mixed $return, ?Throwable $exception) use (&$context) {
     // if you will comment out below line 
     // span will not be updated
     forwardObjectId($object, $return, $context);
     return $return;
   }
);

function handle(BasicRequest $request, Context &$context) {
  $span = new Span;
  $span->name = "undefined";
  $context->spans[spl_object_id($request)] = $span;
  $request = $request->withAttribute("attr1", 1);  
  return $request;
}

function performRouting(BasicRequest $request, Context &$context) {
  if (array_key_exists(spl_object_id($request), $context->spans)) {
    $context->spans[spl_object_id($request)]->name = "root";
  }
}

$context = new Context;
$request = new BasicRequest;

$req = handle($request, $context);
performRouting($req, $context);

foreach ($context->spans as $span) {
  echo $span->name;
}

?>
