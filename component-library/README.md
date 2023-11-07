app.php

use webdna\componentlibrary\ComponentLibrary;

return [
	'modules' => [
		'component-library' => ComponentLibrary::class,
	],
	'bootstrap' => ['component-library'],
];