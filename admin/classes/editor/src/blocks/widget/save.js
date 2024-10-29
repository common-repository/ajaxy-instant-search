/**
 * WordPress dependencies
 */
import { InnerBlocks, useBlockProps } from "@wordpress/block-editor";


export default function save({ attributes }) {	
	return (
		<div {...useBlockProps.save()}></div>
	);
}
