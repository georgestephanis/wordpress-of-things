#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <DHT.h>

const char* wifissid   = "";
const char* wifipass   = "";
const char* wpuser     = "";
const char* wppass     = "";
const char* secret_key = ""; // the uuid for the weather station as given by WordPress

String url           = "https://stephanis.me/wp-json/wordpress-of-things/v1/weather-station/" + String( secret_key );
String fingerprint   = "32:70:4F:7A:80:C1:71:24:80:DA:E5:8C:A1:78:6E:A4:C8:39:56:C7";

// Initialize our connection to the sensor...
DHT dht( D4, DHT11, 11 );
float humidity, temp_f;

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
  humidity = dht.readHumidity();
  temp_f   = dht.readTemperature( true ); // true gives us farenheit!

  if ( isnan( humidity ) || isnan( temp_f ) ) {
    Serial.println( "Failed to read from DHT sensor!" );
    delay( 500 );
    return;
  }

  Serial.println( "Temp " + String( temp_f ) );
  Serial.println( "Humidity " + String( humidity ) );

  // We got the data, now let's send it out!
  HTTPClient http;

  http.begin( url + "?temperature=" + String( temp_f ) + "&humidity=" + String( humidity ), fingerprint );
  http.setAuthorization( wpuser, wppass );
  http.setUserAgent( "George Stephanis | WordCamp Lancaster | WordPress of Things" );

  int httpCode = http.POST("");

  if ( httpCode != HTTP_CODE_OK ) {
    Serial.printf( "[HTTP] GET... failed, error %d: %s\n", httpCode, http.errorToString( httpCode ).c_str() );
    String payload = http.getString();
    Serial.println( payload );
    delay( 5000 );
    return;
  }

  String payload = http.getString();
  Serial.println( payload );

  delay( 5000 );
}
