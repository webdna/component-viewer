# Share links

A share link opens the whole component library for someone with no account, such as a client
reviewing a design. It looks like the control-panel library and does everything it does, apart
from managing links. It works until its expiry date, or until someone cancels it.

Creating and cancelling links needs the **Create and cancel share links** permission (see
[Setup](setup.md#4-permissions)).

## Create a link

1. In the control panel, open **Component Library → Share links**. It's also in the library's
   header.
2. Under **New share link**, enter a **Label** saying who or what it's for, such as
   *Acme: homepage review*. The label heads every page the link opens, so the recipient sees it.
3. Pick when it **Expires**. It's filled in as two weeks from today, and can be anything from
   tomorrow to three months away. The link stops working at the end of that day, UTC.
4. Choose **Create share link**.

The full address opens in a copy prompt, like Craft's *Copy impersonation URL*. To copy it again
later, choose **Copy link** on an active link's row. The address is stored only encrypted with the
site's security key (`CRAFT_SECURITY_KEY`), so a copy of the database alone can't open a link.
Changing that key makes existing links uncopyable (they still work), so cancel and remake any
you need to copy again.

Nothing is emailed. Send the address yourself, the way you'd send any link.

## What the recipient sees

The link opens `https://<your primary site>/component-library/share/<code>`, headed with the label
and the expiry date. With no login, the recipient can:

- browse and search every component
- change settings, pick examples and switch sites
- preview on desktop, tablet or phone
- read each component's source and notes

They never see file paths or folder names. Whatever they type into a setting reaches the
component as plain text, so a link can't be used to put markup or site content into a page.
Previews render as a signed-out visitor would see them.

Each view has its own address under the link, so they can send you a particular component and
example.

## The list

**Share links** lists every link with its label, who created it, its expiry, its status
(*Active*, *Expired* or *Cancelled*) and when it was last used. *Last used* updates at most once a
minute.

## Cancel a link

Choose **Cancel link** on its row and confirm. It stops working straight away for everyone,
including anyone who has it open: their next click or preview is refused. A cancelled link can't be
restarted, so make a new one if it's needed again.

## When a link stops working

| The link is | The recipient sees |
|---|---|
| Expired | *This link has expired. Ask whoever sent it for a new link.* |
| Cancelled | *This link has been cancelled. Ask whoever sent it for a new link.* |
| Mistyped or unknown | *This link doesn't work. Check that the address is complete, or ask whoever sent it for a new link.* |

## Housekeeping

- Expired and cancelled links are deleted 30 days later, by Craft's garbage collection.
- Deleting a user deletes the links they created, which stops them working.
- A link records only its label, its creator and its dates. Nothing about who it was sent to.
- There's no limit on how many links you make. One per recipient makes it easy to cancel just one.
