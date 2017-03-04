#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

const char* wifissid = "IU13-Conference Center";
const char* wifipass = "";

String url           = "http://wp-of-things.pw/wp-json/wp/v2/posts/?per_page=1";

void setup() {
  Serial.begin( 74880 );

  Serial.println();
  Serial.println();
  Serial.printf( "Connecting to %s ", wifissid );

  WiFi.begin( wifissid, wifipass );

  while ( WiFi.status() != WL_CONNECTED ) {
    delay( 500 );
    Serial.print( "." );
  }

  Serial.println();
  Serial.println( "WiFi connected" );
  Serial.print( "IP address: " );
  Serial.println( WiFi.localIP() );
}

void loop() {

  HTTPClient http;

  http.begin( url );

  int httpCode = http.GET();

  if ( httpCode != HTTP_CODE_OK ) {
    Serial.printf( "[HTTP] GET... failed, error %d: %s\n", httpCode, http.errorToString( httpCode ).c_str() );
    String payload = http.getString();
    Serial.println( payload );
    delay( 5000 );
    return;
  }

  String payload = http.getString();

  int start = payload.indexOf( "\"date\":\"" ) + 8;
  String date = payload.substring( start );
  date = date.substring( 0, date.indexOf( '"' ) );

  start = payload.indexOf( "\"title\":{\"rendered\":\"" ) + 21;
  String title = payload.substring( start );
  title = title.substring( 0, title.indexOf( '"' ) );
  
  Serial.println( "Most recent post (" + title + ") was published on " + date );

  delay( 5000 );
}
