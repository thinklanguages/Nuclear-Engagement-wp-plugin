import { existsSync } from 'fs';
if (!existsSync('./tsconfig.json')) {
  console.error('tsconfig.json not found');
  process.exit(1);
}
