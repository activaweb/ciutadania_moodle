<?php
$string['pluginname'] = 'CiutadanIA Certifications';
$string['mencions_definition'] = 'Mencions / tracks definition';
$string['mencions_definition_help'] = 'One menció per line, formatted as <code>Menció name = modules</code>. Modules are referenced by number (idnumber <code>M&lt;num&gt;</code>), comma-separated; ranges are supported (e.g. <code>1-4,10</code>). Use <code>*</code> as the module list to require ALL course modules.<br><br>Example:<br><code>Cursos obligatoris = 1,2,3,4,10<br>Dades i suport a la decisió = 1,2,3,4,10,12,14<br>Percepció = 1,2,3,4,5,8,9,10,13<br>CiutadanIA 2.0 al complet = *</code><br><br>Changes apply immediately to the next certificates/diplomas generated, no code deployment needed.';
$string['privacy:metadata:ciudadania_certifications'] = 'Stores certification snapshots for users';
$string['privacy:metadata:ciudadania_certifications:userid'] = 'User ID';
$string['privacy:metadata:ciudadania_certifications:courseid'] = 'Course ID';
$string['privacy:metadata:ciudadania_certifications:modules_json'] = 'JSON data of certified modules';
$string['privacy:metadata:ciudadania_certifications:total_hours'] = 'Total certified hours';
$string['privacy:metadata:ciudadania_certifications:timecreated'] = 'Time when certification was created';
