<?php
$string['pluginname']    = 'Text amb variables';
$string['mode']          = 'Mode de dades';
$string['mode_help']     = 'Tria quines dades fan servir les variables {total_moduls}, {nota_mitja}, {total_hores} i les de data:<br>• <strong>Mòduls aprovats actuals</strong>: sempre reflecteix el progrés en viu (per al diploma gratuït)<br>• <strong>Últims mòduls certificats</strong>: només s\'actualitza en el proper pagament (per al certificat oficial)';
$string['mode_current']  = 'Mòduls aprovats actuals (dinàmic)';
$string['mode_certified'] = 'Últims mòduls certificats (de l\'últim pagament)';
$string['template']      = 'Text de la plantilla';
$string['template_help'] = 'Escriu el text que apareixerà al certificat. Pots usar les variables següents:

{nom_complet} — Nom i cognoms de l\'estudiant
{nom} — Nom de pila
{cognoms} — Cognoms
{dni_nif} — Número d\'identificació (camp "ID number" del perfil d\'usuari)
{total_moduls} — Nombre de mòduls aprovats (≥ 35) al certificat
{nota_mitja} — Nota mitjana dels mòduls aprovats (sobre 100, 1 decimal)
{total_hores} — Total d\'hores de formació (mòduls × 2)
{nom_curs} — Nom del curs
{data_inici_curs} — Data de matriculació al curs
{data_finalitzacio_modul_mes_recent} — Data de compleció del mòdul acabat més recentment
{numero_referencia_certificat} — Referència única del certificat (ex: CERT-2026-00042)
{data_emissio} — Data d\'emissió del certificat (= data del pagament)
{data_avui} — Data actual (moment d\'impressió)
{mencions} — Llista (separada per comes) de les mencions/itineraris assolits amb els mòduls d\'aquest certificat. Es configuren a Arranjament del lloc → Plugins → Complements locals → Certificacions CiutadanIA';
$string['nopaymentmade'] = 'Encara no s\'ha realitzat cap pagament.';
