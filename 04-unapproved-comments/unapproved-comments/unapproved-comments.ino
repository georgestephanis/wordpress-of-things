
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <Adafruit_NeoPixel.h>

Adafruit_NeoPixel neoring = Adafruit_NeoPixel( 16, D5 );

const char* wifissid = "IU13-Conference Center";
const char* wifipass = "";
const char* wpuser   = "georgestephanis";
const char* wppass   = "KCZT Dfp2 ztwS ii8U lVBa Cw9e";

String url           = "https://wp-of-things.pw/wp-json/wp/v2/comments/?status=0";
//         Or, maybe = "https://wp-of-things.pw/wp-json/wc/v2/orders/?status=processing";
//         for unshipped orders in WooCommerce?
String fingerprint   = "3B:EE:BF:18:77:FA:12:24:31:08:FD:6D:E1:81:9D:17:23:98:ED:74";

const char* headerKeys[] = {
  "X-WP-Total"
};
size_t headerKeysSize = sizeof( headerKeys ) / sizeof( char* );

void setAllPixelsToColor( int r, int g, int b ) {
  setXPixelsToColor( 16, r, g, b );
}

void setXPixelsToColor( int x, int r, int g, int b ) {
  if ( x > 16 ) {
    x = 16; // We only have 16 leds.
  }

  for ( int i = 0; i < x; i++ ) {
    neoring.setPixelColor( i, neoring.Color( r, g, b ) );
  }
  neoring.show();
}

void setup() {
  neoring.begin();
  setAllPixelsToColor( 255, 165, 0 ); // orange

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

  http.begin( url, fingerprint );
  http.setAuthorization( wpuser, wppass );
  http.collectHeaders( headerKeys, headerKeysSize );

  int httpCode = http.GET();

  if ( httpCode != HTTP_CODE_OK ) {
    Serial.printf( "[HTTP] GET... failed, error %d: %s\n", httpCode, http.errorToString( httpCode ).c_str() );
    String payload = http.getString();
    Serial.println( payload );
    setAllPixelsToColor( 255, 0, 0 );
    delay( 5000 );
    return;
  }

  // String payload = http.getString();
  // Serial.println( payload );

  String qty = http.header( headerKeys[0] );

  Serial.println( "Found " + qty + " result(s)!" );

  setAllPixelsToColor( 0, 0, 0 );
  setXPixelsToColor( qty.toInt(), 0, 100, 0 ); // green

  delay( 5000 );
}
