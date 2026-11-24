import fs from 'node:fs';

const test = [
  { command: 'page.rename', params: { name: 'Page 1' } },
  { command: 'frame.create', params: { name: 'F1', x: 0, y: 0, width: 100, height: 100 } },
  { command: 'shape.rectangle', params: { name: 'R1', x: 10, y: 10, width: 20, height: 20, parent: 2 } },
  { command: 'page.add', params: { name: 'Page 2' } },
  { command: 'frame.create', params: { name: 'F2', x: 0, y: 0, width: 100, height: 100 } },
  { command: 'shape.rectangle', params: { name: 'R2', x: 10, y: 10, width: 20, height: 20, parent: 4 } }
];

fs.writeFileSync('scratch/test_id.json', JSON.stringify(test, null, 2));
