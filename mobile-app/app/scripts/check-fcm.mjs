// node scripts/check-fcm.mjs [--android FILE] [--ios FILE] [--key FILE] [--offline]
//
// Checks the three Firebase files before you upload anything, and prints the
// .env lines that go with them. Nothing is changed or sent anywhere except one
// token request to Google (skip it with --offline).
//
//   --android  google-services.json          (default android/app/google-services.json)
//   --ios      GoogleService-Info.plist      (default ios/App/App/GoogleService-Info.plist)
//   --key      service-account JSON for the server (no default: it never lives in the repo)

import { createSign } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';

const APP_ID = 'club.campbuddy.app';
const root = new URL('../', import.meta.url);
const flag = (name, fallback) => {
  const i = process.argv.indexOf(`--${name}`);
  return i > -1 ? process.argv[i + 1] : fallback;
};

const androidPath = flag('android', new URL('android/app/google-services.json', root).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const iosPath = flag('ios', new URL('ios/App/App/GoogleService-Info.plist', root).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const keyPath = flag('key', null);
const offline = process.argv.includes('--offline');

let problems = 0;
const ok = (msg) => console.log(`  OK    ${msg}`);
const bad = (msg) => {
  problems++;
  console.log(`  FIX   ${msg}`);
};
const missing = (msg) => console.log(`  --    ${msg}`);

let projectId = null;

console.log('\nAndroid: google-services.json');
if (!existsSync(androidPath)) {
  missing(`not found at ${androidPath} (Firebase console > Project settings > Your apps > Android)`);
} else {
  try {
    const json = JSON.parse(readFileSync(androidPath, 'utf8'));
    projectId = json.project_info?.project_id ?? null;
    const packages = (json.client ?? []).map((c) => c.client_info?.android_client_info?.package_name);
    packages.includes(APP_ID) ? ok(`package ${APP_ID}`) : bad(`the file is for ${packages.join(', ') || 'no app'}, not ${APP_ID}: add an Android app with that exact package`);
    projectId ? ok(`project ${projectId}`) : bad('no project_id in the file');
  } catch {
    bad('not valid JSON: download it again from Firebase');
  }
}

console.log('\niOS: GoogleService-Info.plist');
if (!existsSync(iosPath)) {
  missing(`not found at ${iosPath} (Firebase console > Project settings > Your apps > iOS)`);
} else {
  const plist = readFileSync(iosPath, 'utf8');
  const value = (key) => new RegExp(`<key>${key}</key>\\s*<string>([^<]*)</string>`).exec(plist)?.[1] ?? null;
  value('BUNDLE_ID') === APP_ID ? ok(`bundle id ${APP_ID}`) : bad(`BUNDLE_ID is ${value('BUNDLE_ID')}, must be ${APP_ID}`);
  const iosProject = value('PROJECT_ID');
  if (iosProject && projectId && iosProject !== projectId) bad(`this plist is for project ${iosProject}, but google-services.json is for ${projectId}: use one Firebase project for both`);
  else if (iosProject) ok(`project ${iosProject}`);
  projectId ??= iosProject;
}

console.log('\nServer: service-account key');
let credentials = null;
if (!keyPath) {
  missing('pass --key /path/to/service-account.json (Firebase > Project settings > Service accounts > Generate new private key)');
} else if (!existsSync(keyPath)) {
  bad(`${keyPath} not found`);
} else {
  try {
    credentials = JSON.parse(readFileSync(keyPath, 'utf8'));
    credentials.type === 'service_account' && credentials.client_email && credentials.private_key ? ok(`service account ${credentials.client_email}`) : bad('this is not a service-account key file');
    if (projectId && credentials.project_id !== projectId) bad(`key is for project ${credentials.project_id}, the app files are for ${projectId}`);
    else ok(`project ${credentials.project_id}`);
  } catch {
    bad('not valid JSON');
  }
}

if (credentials?.private_key && !offline) {
  console.log('\nGoogle: does the key really work?');
  try {
    const now = Math.floor(Date.now() / 1000);
    const b64 = (value) => Buffer.from(typeof value === 'string' ? value : JSON.stringify(value)).toString('base64url');
    const unsigned = `${b64({ alg: 'RS256', typ: 'JWT' })}.${b64({ iss: credentials.client_email, scope: 'https://www.googleapis.com/auth/firebase.messaging', aud: 'https://oauth2.googleapis.com/token', iat: now, exp: now + 3600 })}`;
    const signature = createSign('RSA-SHA256').update(unsigned).sign(credentials.private_key).toString('base64url');
    const response = await fetch('https://oauth2.googleapis.com/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer', assertion: `${unsigned}.${signature}` }),
    });
    const body = await response.json();
    body.access_token ? ok('Google accepted the key (an access token was issued)') : bad(`Google refused it: ${body.error_description ?? body.error}`);
  } catch (error) {
    bad(`could not reach Google: ${error.message}`);
  }
}

console.log('\nServer .env (live site):');
console.log('  FCM_CREDENTIALS_PATH=/home/<user>/secrets/campbuddy-fcm.json');
if (projectId) console.log(`  FCM_PROJECT_ID=${projectId}   (optional: the key file already names it)`);
console.log('  MOBILE_FEATURE_NATIVE_PUSH=true');

console.log(problems ? `\n${problems} thing(s) to fix.\n` : '\nAll the files that were given look right.\n');
process.exit(problems ? 1 : 0);
