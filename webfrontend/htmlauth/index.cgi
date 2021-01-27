#!/usr/bin/perl


##########################################################################
# LoxBerry-Module
##########################################################################
use CGI;
use LoxBerry::System;
use LoxBerry::Web;
  
# Die Version des Plugins wird direkt aus der Plugin-Datenbank gelesen.
my $version = LoxBerry::System::pluginversion();

# Mit dieser Konstruktion lesen wir uns alle POST-Parameter in den Namespace R.
my $cgi = CGI->new;
$cgi->import_names('R');
# Ab jetzt kann beispielsweise ein POST-Parameter 'form' ausgelesen werden mit $R::form.

 
# Wir Übergeben die Titelzeile (mit Versionsnummer), einen Link ins Wiki und das Hilfe-Template.
# Um die Sprache der Hilfe brauchen wir uns im Code nicht weiter zu kümmern.
LoxBerry::Web::lbheader("AWV Ostthüringen Plugin V$version", "http://www.loxwiki.eu/AWV-OT/", "help.html");
  
# Wir initialisieren unser Template. Der Pfad zum Templateverzeichnis steht in der globalen Variable $lbptemplatedir.

my $template = HTML::Template->new(
    filename => "$lbptemplatedir/index.html",
    global_vars => 1,
    loop_context_vars => 1,
    die_on_bad_params => 0,
	associate => $cgi,
);
  

# Sprachdatei laden
my %L = LoxBerry::Web::readlanguage($template, "language.ini");

##########################################################################
# Process form data
##########################################################################
if ($ENV{SERVER_PORT} != 80) {
	$server_port = ":".$ENV{SERVER_PORT};
}
$template->param( WEBSITE_GET => "http://$ENV{SERVER_NAME}$server_port/plugins/$lbpplugindir/getData.php");
$template->param( LOGDATEI => "/admin/system/tools/logfile.cgi?logfile=$lbplogdir/nut.log&header=html&format=template");

# Nun wird das Template ausgegeben.
print $template->output();
  
# Schlussendlich lassen wir noch den Footer ausgeben.
LoxBerry::Web::lbfooter();
