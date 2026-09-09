<?php
$string['pluginname']    = 'Text with variables';
$string['mode']          = 'Data mode';
$string['mode_help']     = 'Choose which data the {total_moduls}, {nota_mitja}, {total_hores} and date variables use:<br>• <strong>Current approved modules</strong>: always reflects live progress (for a free diploma of attendance)<br>• <strong>Last certified modules</strong>: only updates on the next payment (for the official certificate)';
$string['mode_current']  = 'Current approved modules (live)';
$string['mode_certified'] = 'Last certified modules (from the last payment)';
$string['template']      = 'Template text';
$string['template_help'] = 'Write the text that will appear on the certificate. You can use the following variables:

{nom_complet} — Student\'s full name
{nom} — First name
{cognoms} — Last name
{dni_nif} — ID number (from the "ID number" field in the user profile)
{total_moduls} — Number of approved modules (≥ 35) in the certificate
{nota_mitja} — Average grade of approved modules (out of 100, 1 decimal)
{total_hores} — Total training hours (modules × 2)
{nom_curs} — Course name
{data_inici_curs} — Date of enrolment in the course
{data_finalitzacio_modul_mes_recent} — Completion date of the most recently completed module
{numero_referencia_certificat} — Unique certificate reference (e.g. CERT-2026-00042)
{data_emissio} — Certificate issue date (= payment date)
{data_avui} — Today\'s date (at print time)';
$string['nopaymentmade'] = 'No payment has been made yet.';
