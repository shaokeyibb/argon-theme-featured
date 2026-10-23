import { parse } from '@babel/parser';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
const root=import.meta.dirname;
const blocks=['alert','admonition','collapse','github','timeline','progressbar','todolist','tabpanel'];
function literal(node) {
  if (['StringLiteral','NumericLiteral','BooleanLiteral'].includes(node.type)) return node.value;
  if (node.type === 'NullLiteral') return null;
  if (node.type === 'ArrayExpression') return node.elements.map(literal);
  if (node.type === 'ObjectExpression') return Object.fromEntries(node.properties.map(property => [property.key.name || property.key.value, literal(property.value)]));
  throw new Error(`Block schema must use literal JSON values, got ${node.type}`);
}
for (const name of blocks) {
  const ast=parse(readFileSync(join(root,`src/${name}/${name}.js`),'utf8'),{sourceType:'module',plugins:['jsx']});
  const call=ast.program.body.find(node => node.type==='ExpressionStatement' && node.expression.type==='CallExpression' && node.expression.callee.name==='registerBlockType').expression;
  const fields=call.arguments[1].properties;
  const attributes=literal(fields.find(field=>field.key.name==='attributes').value);
  const titleNode=fields.find(field=>field.key.name==='title').value;
  const title=titleNode.type === 'StringLiteral' ? titleNode.value : titleNode.arguments[0].value;
  const metadata={$schema:'https://schemas.wp.org/trunk/block.json',apiVersion:3,name:`argon/${name}`,title,category:'argon',textdomain:'argon',attributes,editorScript:'argon-blocks',editorStyle:'argon-blocks-editor',supports:{html:false}};
  const directory=join(root,'metadata',name);mkdirSync(directory,{recursive:true});
  writeFileSync(join(directory,'block.json'),JSON.stringify(metadata,null,2)+'\n');
}
