import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement, cloneElement } from '@wordpress/element';
import { legacyBlocks } from './deprecated.js';

/** Keep legacy serialization available while adopting the iframe-compatible API. */
export function registerArgonBlock(name, settings) {
  const LegacyEdit = settings.edit;
  const legacySave = settings.save;
  registerBlockType(name, {
    ...settings,
    apiVersion: 3,
    supports: { ...settings.supports, html: false },
    edit(props) {
      return createElement('div', useBlockProps(), createElement(LegacyEdit, props));
    },
    save(props) {
      const element = legacySave(props);
      return element ? cloneElement(element, useBlockProps.save(element.props)) : null;
    },
    deprecated: [legacyBlocks[name]]
  });
}
