// Copyright 2025 akamoz.jp
//
// This file is part of tiny-filelist.
//
// Tiny-filelist is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// Tiny-filelist program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// Affero General Public License for more details.
//
// You should have received a copy of the Affero GNU General Public License
// along with this program. If not, see <https://www.gnu.org/licenses/>.

import { remarkDefinitionList, defListHastHandlers } from 'remark-definition-list';
import { unified } from 'unified';
import remarkParse from 'remark-parse';
import remarkRehype from 'remark-rehype';
import rehypeRaw from 'rehype-raw'
import rehypeStringify from 'rehype-stringify';
import {toVFile, readSync} from 'to-vfile';
import process from 'node:process';
import remarkGfm from 'remark-gfm';

const html = await unified()
  .use(remarkParse)
  .use(remarkDefinitionList)
  .use(remarkGfm)
  .use(remarkRehype, {
	allowDangerousHtml: true,
    handlers: {
      // any other handlers
      ...defListHastHandlers,
    }
  })
  .use(rehypeRaw)
  .use(rehypeStringify)
  .process(readSync("/dev/stdin"));

process.stdout.write(html.value);
