import fs from 'node:fs';

const files = [
  'resources/scss/abstracts/_variables.scss',
  'resources/css/app.css',
  'resources/js/brand.js',
  'app/Support/BrandTokens.php',
  'app/Support/MobileAppDefaults.php',
  'native-app/shared/src/commonMain/kotlin/club/campbuddy/shared/model/ConfigModels.kt',
  'marketing/usp-campaign/shared/brand.css'
];

for (const rel of files) {
  const p = 'E:/Sites/InHouse/campbuddy/V2/' + rel;
  if (!fs.existsSync(p)) continue;
  let text = fs.readFileSync(p, 'utf8');
  text = text.replace(/#f59e0b/gi, '#d97706');
  fs.writeFileSync(p, text, 'utf8');
  console.log(`Updated ${rel}`);
}
