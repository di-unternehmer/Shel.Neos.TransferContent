# Backend module for transferring content in Neos CMS multi sites

## Introduction

This plugin will add a new backend module for copying nodes between sites in a 
Neos multi site installation.

It's currently compatible with Neos 4.3.

### Warning

This package was built to solve a very specific issue and should only be used by 
website administrators who know what their doing.

Future versions of this package might improve the usability and PRs to do this are very welcome.

Also note that references and links inside the copied nodes are not updated to link to their copied target 
and therefore still link to the site where they were copied from.
                
## Installation

Run this in your site package

    composer require --no-update shel/neos-transfercontent
    
Then run `composer update` in your project directory.

## How to use

In the backend module you can choose to move or to copy a tree.
It's only in the mode `move` possible to keep the dimensions in sync.
So if you want to keep e.g. your translated pages in sync and move them as well, you can do so.
You have to define your dimension you want in a Settings.yaml. An example is available here: `Configuration\Settings.TransferContent.Dimensions.yaml.example`
In the settings are only additional dimension to be set, the default dimension is always used. 

## Contributions

Contributions are very welcome! 

Please create detailed issues and PRs.  

**If you use this package and want to support or speed up it's development, [get in touch with me](mailto:transfercontent@helzle.it).**

Or you can also support me directly via [patreon](https://www.patreon.com/shelzle).

## License

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
