let isProcessing = false;
let upBal = 0;
const MIN_BET = 1e-8;
let oldBal = 0;

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function clickRepeatedly(element, times, interval = 200) {
    if (!element || times <= 0) return;
    for (let i = 0; i < times; i++) {
        element.click();
        if (i < times - 1) await delay(interval);
    }
}

function findButtonByText(text) {
    const buttons = document.getElementsByTagName('button');
    for (let i = 0; i < buttons.length; i++) {
        if (buttons[i].textContent.trim() === text) return buttons[i];
    }
    return null;
}

function findPlayButton() {
    const buttons = document.getElementsByTagName('button');
    for (let i = 0; i < buttons.length; i++) {
        const text = buttons[i].textContent.trim();
        if (text.startsWith('PLAY') && text.includes('ON WIN')) return buttons[i];
    }
    return null;
}

const observer = new MutationObserver(async () => {
    if (isProcessing) return;

    const playBtn = findPlayButton();
    if (!playBtn || playBtn.textContent.trim() === 'CANCEL') return;

    const playBtn2 = document.querySelector('#root > div > div > div.sc-avgGE.chlmIH > div > div > div:nth-child(1) > aside > div > div.sc-eldhqJ.jUspjF > div.sc-eXlCAP.igqHso > div.sc-iFMCWE.ereBYv');
    if (!playBtn2?.textContent?.includes('big wins')) return;

    const countdown = document.querySelector('#root > div > div > div.sc-kiIydw.iSLMZd > div > div.sc-djWQsk.jdimoh > div.sc-jKTeqw.cDKZwi > div > div');
    const join = document.querySelector('[aria-label="Join giveaway"]');
    if ((!countdown || !countdown.textContent.includes('Next giveaway')) && join) {
        setTimeout(() => join.click(), 100);
    }

    const bchHeader = Array.from(document.querySelectorAll('header')).find(el => el.textContent.includes('BCH'));
    if (!bchHeader) return;
    const balanceText = bchHeader.textContent.trim().split(' ')[0].replace(/,/g, '');

    const betElement = document.querySelector('[aria-label="stake"]');
    const increase = document.querySelector('[aria-label="increase stake"]');
    const decrease = document.querySelector('[aria-label="decrease stake"]');
    const openPopupBtn = playBtn.querySelector('svg')?.parentElement;

    if (!balanceText || !betElement || !openPopupBtn) return;

    const bal = parseFloat(balanceText);
    const bet = parseFloat(betElement.value);
    if (Number.isNaN(bal) || Number.isNaN(bet)) return;

    isProcessing = true;

    try {
        if (upBal < bal) {
            upBal = bal;

            // Giảm bet về MIN_BET bằng cách đếm số lần cần chia đôi (/2)
            let tempBet = bet;
            let decreaseCount = 0;
            while (tempBet / 2 >= MIN_BET) {
                decreaseCount++;
                tempBet /= 2;
            }

            if (decreaseCount > 0) {
                await clickRepeatedly(decrease, decreaseCount, 200);
            }
        } else {
            if (2 * bet + bal < upBal && oldBal > bal) {
                await delay(200);
                increase?.click();
            } else if (bet + bal >= upBal && bet > MIN_BET) {
                let tempBet = bet;
                let decreaseCount = 0;
                while (tempBet + bal >= upBal && tempBet > MIN_BET) {
                    decreaseCount++;
                    tempBet /= 2;
                }

                if (decreaseCount > 0) {
                    await delay(200);
                    await clickRepeatedly(decrease, decreaseCount, 200);
                }
            }
        }

        openPopupBtn.click();
        await delay(1000);

        const startBtn = findButtonByText('START AUTOPLAY');
        if (startBtn) {
            oldBal = bal;
            startBtn.click();
        }

        await delay(5000);
    } catch (err) {
        console.error('Lỗi khi thực thi bet autoplay:', err);
    } finally {
        isProcessing = false;
    }
});

observer.observe(document.body, { childList: true, subtree: true });